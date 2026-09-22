<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Re-aplica o inglês sobre arquivos que o upstream devolveu em português.
 *
 * Este comando é o nível "sembuh-diri" do plano de idioma. Um `kit:update`
 * aplica diff com `git checkout <tag> -- <path>` — substituição inteira, sem
 * three-way merge — então qualquer arquivo da área migrada pode voltar a trazer
 * o literal PT. Rodar `i18n:extract` depois do merge desfaz isso em segundos, e
 * `--report --fail` transforma a mesma varredura num alarme de CI.
 *
 * ## O dicionário é o próprio overlay
 *
 * A fonte da verdade é `lang/pt_BR.json`: cada entrada `"English": "Português"`
 * é lida de trás para frente. Não existe uma segunda lista de strings dentro do
 * comando — dois donos para a mesma tradução é a classe de defeito que
 * `.ai/rules/config.md` proíbe.
 *
 * ## Por que só certas formas de chamada
 *
 * Um literal PT idêntico a um rótulo de UI também pode ser um valor de banco
 * comparado em `match`, ou o slug de uma rota. Trocar `"Sim"` por `__('Sim')`
 * dentro de `case 'Sim':` não quebra o PHP — quebra a comparação. Por isso o
 * comando só reescreve o literal quando ele aparece em três contextos:
 *
 *   1. argumento direto de um método de UI (`->label(...)`, `->modalHeading(...)`)
 *   2. valor de um `return` dentro de um getter de rótulo (`getTitle(): 'X'`)
 *   3. valor depois de `=>` (arrays de rótulo, como `Paineis::rotulos()`)
 *
 * A análise é por `token_get_all()`, não por regex sobre o texto: comentário PHPDoc
 * citando um rótulo não é código, e `tests/Pest.php:1219` já documentou três
 * incidentes em que varredura por regex contou comentário. Idempotência sai de
 * graça: um `__('X')` já migrado fica num nível de parêntese mais fundo e não casa.
 */
class I18nExtract extends Command
{
    protected $signature = 'i18n:extract
                            {--path=* : Caminho (arquivo ou pasta) a varrer; o padrão são as superfícies de UI}
                            {--report : Só lista o que voltaria a ser português, sem escrever}
                            {--candidatos : Lista todo literal em forma de UI, mesmo sem entrada no dicionário}
                            {--fail : Sai com código 1 se houver trabalho pendente (para CI)}';

    protected $description = 'Aplica de novo o inglês do projeto sobre o texto português devolvido pelo upstream';

    /** Quantos arquivos o run atual tocou (report ou escrita). */
    private int $arquivosComMudanca = 0;

    /**
     * Métodos cujo argumento é texto de tela.
     *
     * @var list<string>
     */
    private const METODOS_DE_UI = [
        'label', 'description', 'heading', 'subheading', 'title', 'hint', 'tooltip', 'badge',
        'group', 'confirmText', 'modalHeading', 'modalDescription',
        'modalSubmitActionLabel', 'modalCancelActionLabel',
        'emptyStateHeading', 'emptyStateDescription',
    ];

    /** Nome de método cujo `return` é rótulo de tela. */
    private const GETTER_DE_ROTULO = '/(Label|Title|Heading|Description|Subheading)$/';

    public function handle(): int
    {
        $dicionario = $this->dicionario();

        if ($dicionario === []) {
            $this->components->error('lang/pt_BR.json vazio: não há dicionário para aplicar.');

            return self::FAILURE;
        }

        $totalMudanca = 0;

        foreach ($this->arquivos() as $arquivo) {
            $totalMudanca += $this->varrerArquivo($arquivo, $dicionario, (bool) $this->option('report'));
        }

        if ($totalMudanca === 0) {
            $this->components->info(sprintf('Nada em português para aplicar (%d entradas de referência).', count($dicionario)));

            return self::SUCCESS;
        }

        $this->components->warn(sprintf(
            '%s%d literais PT em %d arquivo(s) %s.',
            $this->option('report') ? 'Pendente: ' : '',
            $totalMudanca,
            $this->arquivosComMudanca,
            $this->option('report') ? 'aguardando extração' : 'reescritos para inglês',
        ));

        if ($this->option('fail') && $this->option('report')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Inverte o overlay: cada valor PT passa a apontar para a chave English.
     *
     * @return array<string, string>
     */
    private function dicionario(): array
    {
        $overlay = json_decode((string) file_get_contents(base_path('lang/pt_BR.json')), true, 512, JSON_THROW_ON_ERROR);

        $ptParaEn = [];

        foreach ($overlay as $english => $portugues) {
            if (! is_string($portugues) || $portugues === '') {
                continue;
            }

            // Duas chaves English com o mesmo valor PT: a última venceria e reescreveria
            // a primeira para o inglês errado. Melhor parar alto do que escolher.
            if (isset($ptParaEn[$portugues]) && $ptParaEn[$portugues] !== $english) {
                $this->components->error(sprintf(
                    'Colisão de dicionário: "%s" é valor de "%s" e de "%s".',
                    $portugues,
                    $ptParaEn[$portugues],
                    $english,
                ));

                return [];
            }

            $ptParaEn[$portugues] = $english;
        }

        return $ptParaEn;
    }

    /**
     * @return list<string>
     */
    private function arquivos(): array
    {
        $caminhos = (array) $this->option('path');

        if ($caminhos !== [] && $caminhos !== ['*'] && $caminhos !== [null]) {
            return $caminhos;
        }

        return [
            app_path('Filament'),
            app_path('Policies'),
            app_path('Notifications'),
            app_path('Support'),
            app_path('Ai'),
            app_path('Http'),
        ];
    }

    /**
     * @param  array<string, string>  $dicionario
     */
    private function varrerArquivo(string $arquivo, array $dicionario, bool $apenasRelatorio): int
    {
        if (is_dir($arquivo)) {
            $total = 0;

            $itens = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($arquivo, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($itens as $item) {
                if ($item->getExtension() === 'php') {
                    $total += $this->varrerArquivo(str_replace('\\', '/', $item->getPathname()), $dicionario, $apenasRelatorio);
                }
            }

            return $total;
        }

        if (! is_file($arquivo)) {
            return 0;
        }

        $codigo = (string) file_get_contents($arquivo);
        $tokens = token_get_all($codigo);

        /** @var array<int, array{0: int, 1: int, 2: string}> $substituicoes offset, tamanho, novo trecho */
        $substituicoes = [];
        $profundidade  = 0;

        /** @var array<int, string> método cujo "(" abriu a profundidade atual */
        $chamadaAberta = [];

        /** Getter de rótulo em vigor (nome do `function` mais recente). */
        $metodoAtual = null;
        $emRetorno   = false;

        $total = count($tokens);

        /*
         * `token_get_all()` entrega `[id, texto, LINHA]` — a terceira posição não é
         * offset. Usá-la como offset (como a primeira versão deste comando fazia)
         * escreveu `__('Home')` dentro de cláusulas `namespace`/`use` de 8 arquivos.
         * A posição real é acumulada aqui: os textos dos tokens concatenam exatamente
         * o código-fonte, então somar `strlen()` reproduz o byte de cada um.
         */
        $curso = 0;

        for ($i = 0; $i < $total; $i++) {
            $token         = $tokens[$i];
            $textoDoToken  = is_array($token) ? $token[1] : $token;
            $offsetDoToken = $curso;
            $curso += strlen($textoDoToken);

            if ($token === '(') {
                $profundidade++;

                continue;
            }

            if ($token === ')') {
                unset($chamadaAberta[$profundidade]);
                $profundidade--;

                continue;
            }

            if (is_array($token) && $token[0] === T_FUNCTION) {
                $metodoAtual = $this->nomeDoProximoIdentificador($tokens, $i);

                continue;
            }

            if (is_array($token) && ($token[0] === T_OBJECT_OPERATOR || $token[0] === T_DOUBLE_COLON)) {
                $nome = $this->nomeDoProximoIdentificador($tokens, $i);

                if ($nome !== null) {
                    $chamadaAberta[$profundidade + 1] = $nome;
                }

                continue;
            }

            if (is_array($token) && $token[0] === T_RETURN) {
                $emRetorno = true;

                continue;
            }

            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                if (! is_array($token) || $token[0] !== T_WHITESPACE) {
                    $emRetorno = false;
                }

                continue;
            }

            $emContextoDeRetorno = $emRetorno
                && is_string($metodoAtual)
                && preg_match(self::GETTER_DE_ROTULO, $metodoAtual) === 1;

            $emArguimentoDeUI = ($chamadaAberta[$profundidade] ?? null) !== null
                && in_array((string) $chamadaAberta[$profundidade], self::METODOS_DE_UI, true);

            $emValorDeArray = $this->proximoAnteriorSignificativo($tokens, $i) === T_DOUBLE_ARROW;

            $emRetorno = false;

            if (! $emContextoDeRetorno && ! $emArguimentoDeUI && ! $emValorDeArray) {
                continue;
            }

            $conteudo = substr($token[1], 1, -1);

            // Aspas simples escapadas: `\'` e `\\` são os dois casos que um literal PT real usa.
            $texto = str_contains($token[1], "'") ? stripcslashes($conteudo) : $conteudo;

            if (! isset($dicionario[$texto])) {
                if ($this->option('candidatos')) {
                    $this->line(sprintf('%s:%d  "%s"', $arquivo, $token[2], $texto));
                }

                continue;
            }

            $english   = $dicionario[$texto];
            $emRetorno = false;

            if ($apenasRelatorio) {
                $this->line(sprintf(
                    '%s:%d  "%s"  ->  __(\'%s\')',
                    $arquivo,
                    $token[2],
                    $texto,
                    $english,
                ));
            }

            $substituicoes[] = [$offsetDoToken, strlen($textoDoToken), '__('.$this->aspasSimples($english).')'];
        }

        if ($substituicoes === []) {
            return 0;
        }

        $this->arquivosComMudanca++;

        if ($apenasRelatorio) {
            return count($substituicoes);
        }

        foreach (array_reverse($substituicoes) as [$offset, $comprimento, $novo]) {
            $codigo = substr_replace($codigo, $novo, $offset, $comprimento);
        }

        file_put_contents($arquivo, $codigo);

        return count($substituicoes);
    }

    /**
     * Nome do identificador seguinte, pulando espaço em branco e comentário.
     *
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function nomeDoProximoIdentificador(array $tokens, int $desde): ?string
    {
        for ($i = $desde + 1, $total = count($tokens); $i < $total; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }

    /**
     * Tipo do token não-branco anterior, ou null no começo do arquivo.
     *
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function proximoAnteriorSignificativo(array $tokens, int $desde): ?int
    {
        for ($i = $desde - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                continue;
            }

            return is_array($token) ? $token[0] : null;
        }

        return null;
    }

    private function aspasSimples(string $texto): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $texto)."'";
    }
}

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
     * Não são só componentes de schema: `->navigationLabel()` e
     * `->pluralModelLabel()` do REGISTRO de um Resource (o `AdminPanelProvider`
     * faz isso para o `RoleResource` do Shield) também escrevem rótulo, e nada
     * ali passa pelo `translateLabel()` do Filament — sem entrar nesta lista,
     * o item de menu fica português para sempre mesmo com a overlay certa.
     *
     * @var list<string>
     */
    private const METODOS_DE_UI = [
        'label', 'description', 'heading', 'subheading', 'title', 'hint', 'tooltip', 'badge',
        'group', 'confirmText', 'modalHeading', 'modalDescription',
        'modalSubmitActionLabel', 'modalCancelActionLabel',
        'emptyStateHeading', 'emptyStateDescription',
        'navigationLabel', 'modelLabel', 'pluralModelLabel', 'groupLabel', 'navigationGroup',
        'successNotificationTitle', 'failureNotificationTitle',
        'confirm', 'notice', 'body', 'subtitle',
        'confirm', 'notice',
        // Buraco medido no navegador: as descrições longas da tela "Configurações da
        // aplicação" vivem em `helperText()`, e ficaram portuguesas depois de duas
        // rodadas de extração porque só `description`/`hint` estavam na lista.
        'helperText', 'inlineHelp', 'placeholder', 'message', 'errorMessage',
        'confirmDescription', 'confirmHeading', 'emptyStateButton',
    ];

    /**
     * Classes cujo PRIMEIRO argumento de `make()` é texto de tela.
     *
     * A lista é curta de propósito e cada entrada tem assinatura conferida no
     * vendor: `Section::make(string|array|Htmlable|Closure|null $heading)`,
     * `Tab::make(...$label)`, `Text::make(...$content)` e
     * `StatPlus::make(string $label, ...)`. `TextColumn::make()` e
     * `Action::make()` recebem um NOME, não um rótulo — incluí-los aqui
     * reescreveria identificadores.
     *
     * @var list<string>
     */
    private const CLASSES_COM_ARG1_LABEL = ['Section', 'Tab', 'Text', 'StatPlus'];

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

        /*
         * Variante SEM acento de cada valor PT. O motivo é medido, não teórico:
         * `ConfiguracoesDoKit.php` carregava `'Ja configurada — em branco mantem'`,
         * português sem acento escrito à mão, e nem o guarda nem este extrator viam
         * nada — o dicionário só conhecia `'Já configurada — em branco mantém'`.
         * Quando um `kit:update` devolve um arquivo nesse estilo, a recuperação
         * automática depende desta linha.
         *
         * Só entra se o dobrado não existir como chave exata: PT sem acento que já é
         * frase de outra chave não tem direito de roubar o valor dela.
         */
        foreach ($ptParaEn as $portugues => $english) {
            $dobrado = self::semAcento($portugues);

            if ($dobrado === $portugues || isset($ptParaEn[$dobrado])) {
                continue;
            }

            $ptParaEn[$dobrado] = $english;
        }

        return $ptParaEn;
    }

    /**
     * Tira só o acento — o resto da frase (inclusive travessão e espaços) tem de
     * continuar batendo byte por byte, senão não é "mesma frase sem acento".
     */
    private static function semAcento(string $texto): string
    {
        return str_replace(
            ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ', 'ý', 'Á', 'À', 'Â', 'Ã', 'É', 'È', 'Ê', 'Í', 'Ó', 'Ò', 'Ô', 'Õ', 'Ú', 'Ù', 'Ç'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n', 'y', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'I', 'O', 'O', 'O', 'O', 'U', 'U', 'C'],
            $texto,
        );
    }

    /**
     * @return list<string>
     */
    private function arquivos(): array
    {
        /** @var list<string> $caminhos */
        $caminhos = array_values(array_filter(array_map(
            static fn (mixed $p): string => trim((string) $p),
            (array) $this->option('path'),
        )));

        if ($caminhos !== []) {
            return $caminhos;
        }

        return [
            app_path('Filament'),
            // Os plugins de painel registram rótulo AQUI (`FilamentShieldPlugin::make()
            // ->navigationLabel(...)`), e um provider de painel tem mais texto de tela
            // que muito Resource.
            app_path('Providers'),
            app_path('Policies'),
            app_path('Notifications'),
            app_path('Support'),
            app_path('Ai'),
            app_path('Http'),
            app_path('Livewire'),
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

        /** @var array<int, int> posição do argumento dentro da chamada da profundidade */
        $indiceDoArgumento = [];

        /** Último `T_STRING` visto — é o nome da classe que antecede um `::`. */
        $identificadorAnterior = null;

        /** Getter de rótulo em vigor (nome do `function` mais recente). */
        $metodoAtual = null;
        $emRetorno   = false;

        /** Dentro de `#[...]`: nada ali pode virar chamada de função. */
        $emAtributo = false;

        /** Profundidade de `[` contada só para fechar o atributo. */
        $colchetes = 0;

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

            if (is_array($token) && $token[0] === T_STRING) {
                $identificadorAnterior = $token[1];
            }

            if ($token === '(') {
                $profundidade++;
                $indiceDoArgumento[$profundidade] = 1;

                continue;
            }

            if ($token === ')') {
                unset($chamadaAberta[$profundidade], $indiceDoArgumento[$profundidade]);
                $profundidade--;

                continue;
            }

            if ($token === ',') {
                $indiceDoArgumento[$profundidade] = ($indiceDoArgumento[$profundidade] ?? 1) + 1;

                continue;
            }

            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                // `#[...]`: argumento de atributo é EXPRESSÃO CONSTANTE no PHP, então
                // `__()` ali não é inadequado só de gosto — é `Constant expression
                // contains invalid operations`, e o arquivo morre em runtime. Medido:
                // o rewrite de uma mensagem dentro de `#[Validate(...)]` quebrou
                // `app/Livewire/AssistenteChatWidget.php`.
                $emAtributo = true;

                continue;
            }

            if ($token === '[') {
                $colchetes++;

                continue;
            }

            if ($token === ']') {
                if ($colchetes > 0) {
                    $colchetes--;
                } elseif ($emAtributo) {
                    $emAtributo = false;
                }

                continue;
            }

            if (is_array($token) && $token[0] === T_FUNCTION) {
                $metodoAtual = $this->nomeDoProximoIdentificador($tokens, $i);

                continue;
            }

            if (is_array($token) && ($token[0] === T_OBJECT_OPERATOR || $token[0] === T_DOUBLE_COLON)) {
                $nome = $this->nomeDeMetodoQueAbreParentesis($tokens, $i);

                if ($nome !== null) {
                    // `Foo::make(` carrega o nome da classe junto: só algumas têm rótulo no 1º arg.
                    $chamadaAberta[$profundidade + 1] = $token[0] === T_DOUBLE_COLON && $nome === 'make'
                        ? 'make:'.(string) $identificadorAnterior
                        : $nome;
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

            $chamada = $chamadaAberta[$profundidade] ?? null;

            // Arg-1 de `Section::make('…')`, `Tab::make('…')`, `Text::make('…')`, `StatPlus::make('…', …)`.
            $classeDoMake = is_string($chamada) && str_starts_with($chamada, 'make:')
                ? substr($chamada, 5)
                : null;

            $emArguimentoDeUI = is_string($chamada)
                && ($indiceDoArgumento[$profundidade] ?? 0) === 1
                && (in_array($chamada, self::METODOS_DE_UI, true)
                    || in_array((string) $classeDoMake, self::CLASSES_COM_ARG1_LABEL, true));

            $emValorDeArray = $this->proximoAnteriorSignificativo($tokens, $i) === T_DOUBLE_ARROW;

            $emRetorno = false;

            if ($emAtributo) {
                continue;
            }

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

        /*
         * O arquivo só é gravado se o resultado AINDA COMPILAR.
         *
         * `token_get_all()` não basta e isto foi medido: o rewrite que quebrou
         * `app/Livewire/AssistenteChatWidget.php` produziria `Constant expression
         * contains invalid operations` — erro de COMPILE, não de tokenização. Um
         * reescritor que escreve código morto e depois confia no `php -l` do CI
         * estaria entregando o defeito, não descobrindo.
         */
        if ($this->naoCompila($codigo)) {
            $this->components->error("{$arquivo}: resultado não compila; arquivo deixado intacto.");

            return 0;
        }

        file_put_contents($arquivo, $codigo);

        return count($substituicoes);
    }

    /**
     * Nome do método logo após `->`/`::`, mas apenas quando ele abre parênteses.
     *
     * Exigir o `(` não é cerimônia: `Foo::class` e `Bar::CONST` também são
     * `T_STRING` depois de `::`, e registrá-los como chamada aberta deixaria uma
     * entrada envelhecida na profundidade — pronta para casar com a próxima
     * chamada que abrir no mesmo nível e reescrever um argumento que ninguém
     * pediu para tocar.
     *
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function nomeDeMetodoQueAbreParentesis(array $tokens, int $desde): ?string
    {
        $nome = null;

        for ($i = $desde + 1, $total = count($tokens); $i < $total; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                continue;
            }

            if ($nome === null) {
                if (! is_array($token) || $token[0] !== T_STRING) {
                    return null;
                }

                $nome = $token[1];

                continue;
            }

            return $token === '(' ? $nome : null;
        }

        return null;
    }

    /**
     * O resultado ainda compila? `php -l` num arquivo temporário.
     *
     * Erro de expressão constante em atributo (`#[Validate(message: ['a' => __('X')])]`)
     * só aparece na compilação — tokenizar não vê.
     */
    private function naoCompila(string $codigo): bool
    {
        $temporario = tempnam(sys_get_temp_dir(), 'i18n-');

        file_put_contents($temporario, $codigo);

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temporario).' 2>&1', $saida, $codigoSaida);

        @unlink($temporario);

        return $codigoSaida !== 0;
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

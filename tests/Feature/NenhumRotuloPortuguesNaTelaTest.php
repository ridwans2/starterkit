<?php

/*
|--------------------------------------------------------------------------
| Guarda "nenhuma tela devolve português"
|--------------------------------------------------------------------------
| As guardas estáticas (`LabelPainelSudahInggrisTest`) leem código. Esta lê a
| TELA: para cada página do painel, pede o HTML renderizado em inglês e afirma
| que nenhuma frase do overlay `lang/pt_BR.json` aparece ali.
|
| É o alarme que fecha a lacuna da varredura estática: um `kit:update` que
| devolve um arquivo no formato antigo — propriedade estática, heading de
| seção, Blade, o que for — não tem nenhum `return '…'` para ser pego por
| reflexão, mas tem uma frase portuguesa na tela. Aqui ela aparece.
|
| Roda em `en` de propósito: a suíte é pinada em pt_BR (os testes do upstream
| afirmam sobre o português), e é exatamente por isso que a checagem precisa
| trocar de idioma e devolver o estado no `finally`.
*/

use App\Providers\LocalizacaoProvider;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/*
 * O texto que o usuário VÊ: remove `<script>`, `<style>` e `<template>` junto com
 * o conteúdo (payload do Livewire, coluna oculta, tooltip que não está aberto),
 * e só então descarta as tags. Helper de arquivo único, então mora aqui — ver
 * `.ai/rules/testes.md`.
 */
function textodeTela(string $html): string
{
    $limpa = (string) preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#is', '', $html);

    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($limpa), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/*
 * Tudo é calculado DENTRO do `it()`. No topo, em Pest, o arquivo roda na CARGA —
 * antes de a aplicação existir — e `base_path()`/`Route::getRoutes()` ali morrem
 * com "Call to undefined method Container::basePath()".
 */
it('não mostra uma única frase portuguesa em nenhuma tela do painel', function (): void {
    /**
     * Frase PT yang juga dipakai sebagai Inggris oleh paket vendor ('E-mail' é o
     * rótulo de e-mail em inglês no `en.json` do Filament) não é defeito deste
     * projeto — é a ortografia inglesa do pacote. Sem este filtro, o denuncia
     * strings que nem o overlay pode consertar.
     *
     * @var array<string, true> $inglesDoVendor
     */
    $inglesDoVendor = [];

    foreach (array_merge(
        glob(base_path('vendor/*/*/resources/lang/en.json')) ?: [],
        glob(base_path('vendor/*/*/resources/lang/en/*.php')) ?: [],
    ) as $arquivoDoVendor) {
        $isi = str_ends_with($arquivoDoVendor, '.json')
            ? (json_decode((string) file_get_contents($arquivoDoVendor), true, 512) ?: [])
            : (include $arquivoDoVendor);

        foreach ((array) $isi as $valorDoVendor) {
            if (is_string($valorDoVendor)) {
                $inglesDoVendor[mb_strtolower($valorDoVendor)] = true;
            }
        }
    }

    /**
     * Frases do overlay PT que NÃO devem aparecer numa tela em inglês.
     *
     * Encurtadas a partir do arquivo: abaixo de 4 caracteres, uma "frase"
     * portuguesa ('ver', 'sim') casa dentro de palavras inglesas comuns
     * ('preview', 'version') e o guarda viria vermelho sem defeito nenhum.
     *
     * @var list<string> $frasesPortuguesas
     */
    $frasesPortuguesas = array_values(array_filter(
        json_decode((string) file_get_contents(base_path('lang/pt_BR.json')), true, 512, JSON_THROW_ON_ERROR),
        static fn (string $pt): bool => mb_strlen($pt) >= 4 && ! isset($inglesDoVendor[mb_strtolower($pt)]),
    ));

    /** @var list<string> $páginas */
    $páginas = collect(Route::getRoutes())
        ->filter(static fn ($rota): bool => str_contains($rota->methods()[0] ?? '', 'GET'))
        ->map(static fn ($rota): string => $rota->uri())
        ->filter(static function (string $uri): bool {
            // Sem parâmetro (não há registro para injetar), e fora do fluxo de
            // autenticação — essas telas têm guarda própria e redirecionam.
            return ! str_contains($uri, '{')
                && ! preg_match('#^(login|register|password|verify|logout|up|api|_ignition|sanctum|cadastro)#', $uri);
        })
        ->values()
        ->all();

    /*
     * `master_global` vence toda permissão pelo `Gate::before`, então é a única
     * persona que alcança as três painéis de uma vez. A suíte de teste não semea
     * papéis, por isso ele é criado aqui — sem isso o request viraria 403 e o
     * loop fiscalizaria zero telas.
     */
    Role::findOrCreate('master_global', 'web');
    $admin = usuarioCom('master_global');

    /*
     * A fiscalização precisa acontecer com a lista de idiomas DE PRODUÇÃO.
     *
     * `LocalizacaoProvider` de propósito não toca `kit.idiomas` dentro da suíte (os
     * testes do upstream afirmam que o seletor está escondido com um idioma só) — e
     * o seletor de idioma usa ESSA lista como validação. Deixada como está, toda
     * request volta para `pt_BR`, o `setLocale('en')` do teste é desfeito, e o
     * guarda denunciaria português numa tela que na verdade está correta.
     */
    (new LocalizacaoProvider($this->app))->aplicar();

    $violacoes      = [];
    $contexto       = [];
    $quebradas      = [];
    $telasRendered  = 0;
    $localeOriginal = app()->getLocale();

    /** @var array<string, list<string>> $prosa palavras com cara de português por tela */
    $prosa = [];

    /*
     * Acentos + léxico funcional do português. 'de'/'a'/'o' ficam fora de propósito:
     * casam dentro de inglês e o número deixaria de contar defeito para virar ruído.
     *
     * A lista cresceu por OBSERVAÇÃO, e cada palavra extra tem um motivo medido:
     * 'convites?' sozinho não vê 'Convidar em massa' (verbo, não substantivo), e o
     * guarda ficou verde numa tela que mostrava exatamente isso. 'vinculo', 'massa',
     * 'lote', 'guardada', 'cifrad' e 'enviad' entraram pela mesma porta — palavra que
     * o português escreve e o inglês não, sem acento, e portanto invisível para a
     * primeira alternativa do `|`.
     */
    $padraoPt = '/\p{L}*[àáâãäçèéêëìíîïòóôõöùúûü]\p{L}*|\b(e-mail|senha|n[aã]o|voc[êe]|usu[áa]ri[oa]s?|organiza[cç][aã]o|convite[sd]?|convidar|convidad[oa]|pap[ée]is|papel|situa[cç][aã]o|vers[ãa]o|modelo|ativo|inativo|pendentes?|criad[oa]|atualizad[oa]|expir[ao]u?|r[oú]tulo|escolha|confirmar|redefinir|definir|aceitar|recusar|pain[ée]is?|tela|quem|como|quando|nesta|nesse|para|por|sem|um|uma|dos|das|na|vinculo[s]?|massa|lote|guardada|cifrad[oa]|enviad[oa]s?|reenviad[oa]|falha|erro|dados|nome|endere[cç]o|conte[uú]do|registro|registros|salvar|salvou|pr[oö]xim[oa]|anterior|voltar|entrar|sair|hoje|ontem|amanh[ãa]|sempre|nunca|apenas|tamb[ée]m|ent[ãa]o|depois|antes|durante|atrav[ée]s|deve|devem|pode|podem|tem|t[êe]m|s[ãa]o|foi|estar|est[ãa]|est[ao]s|nosso[s]?|nossa[s]?|esse|essa|este|esta|isso|aquele|aquela)\b/iu';

    try {
        app()->setLocale('en');

        foreach ($páginas as $uri) {
            $resposta = $this->actingAs($admin)->get('/'.ltrim($uri, '/'));

            if (app()->getLocale() !== 'en') {
                // Verde sem fiscalizar é pior que vermelho: melhor acusar aqui.
                $this->fail(sprintf(
                    'A request de /%s devolveu o app em "%s" — a tela abaixo não foi medida em inglês.',
                    $uri,
                    app()->getLocale(),
                ));
            }

            // 4xx/3xx aqui é caminho que exige contexto (tenant, feature
            // desligada, login): `isSuccessful()` já deixa o redirect fora, e um
            // redirect não é tela neste idioma.
            if (! $resposta->isSuccessful()) {
                // Mas 5xx NÃO é "falta contexto": é tela que quebrou, e sem registrar
                // aqui o guarda só mostra "renderizou poucas telas" — que é como um
                // `Formatos::` quebrado passou a tarde inteira verde-por-ignorado.
                if ($resposta->getStatusCode() >= 500) {
                    $quebradas[] = $uri.' → '.$resposta->getStatusCode();
                }

                continue;
            }

            // Rota que devolve JSON não é tela: o corpo é payload, e o `strip_tags`
            // não tem o que cortar ali. 'today_Errors' de um endpoint de métricas não
            // é português na interface de ninguém.
            if (! str_contains((string) $resposta->headers->get('Content-Type'), 'html')) {
                continue;
            }

            $telasRendered++;

            /*
             * O extrator imita o navegador: tira `<script>`, `<style>` e `<template>`
             * COM o conteúdo (ali o Filament guarda snapshot do Livewire, colunas
             * ocultas da tabela e tooltip — texto que ninguém está lendo), e só então
             * remove as tags.
             *
             * `DOMDocument::loadHTML()` foi tentado antes e é PIOR que inútil aqui:
             * ele trava nos atributos HTML5 do Livewire (`wire:`, `x-on:`, `:` e `@`),
             * devolve corpo vazio com os avisos calados, e o guarda fica verde sem ler
             * nada — inclusive a prosa que o navegador mostra. Verde silencioso é
             * exatamente o modo de falha que `.ai/rules/testes.md` manda temer, por
             * isso o controle positivo abaixo é obrigatório.
             */
            $texto = textodeTela($resposta->getContent());

            $encontradas = array_values(array_filter(
                $frasesPortuguesas,
                /*
                 * A frase só conta quando é uma PALAVRA à parte. Sem as bordas,
                 * 'Aceito' casava dentro de "Aceitos", 'Conta' dentro de "Contains"
                 * e 'Erro' dentro de "Errors" — e o guarda denunciava inglês bom por
                 * coincidência de grafia. As bordas são de LETRA, não `\b`, para não
                 * reprovar frase que termina em pontuação ('…não é aceito.').
                 */
                static fn (string $pt): bool => preg_match('/(?<!\p{L})'.preg_quote($pt, '/').'(?!\p{L})/iu', $texto) === 1,
            ));

            if ($encontradas !== []) {
                sort($encontradas);

                $violacoes[$uri] = $encontradas;

                /*
                 * Janela de texto em volta de cada frase. Vermelho sem contexto vira
                 * "caça às cegas": a frase 'Ativos' pode ser um filtro, uma coluna, um
                 * stat ou um miúdo de página, e as quatro origens pedem quatro correções
                 * diferentes.
                 */
                foreach ($encontradas as $pt) {
                    $posição = preg_match('/(.{0,48})(?<!\p{L})'.preg_quote($pt, '/').'(?!\p{L})(.{0,48})/iu', $texto, $janela);

                    if ($posição === 1) {
                        $contexto[$uri][$pt] = trim($janela[1]).'【'.$pt.'】'.trim($janela[2]);
                    }
                }
            }

            /*
             * Segunda medição, e é ela que responde "a tela está em inglês?".
             *
             * A de cima só enxerga o que JÁ tem entrada no dicionário: prosa de ajuda
             * que nunca foi migrada é invisível para ela. Aqui conta-se o texto
             * aparente que ainda tem cara de português, com ou sem dicionário — e o
             * resultado é travado por baseline, para o número só poder cair.
             */
            if (preg_match_all($padraoPt, $texto, $c) > 0) {
                /*
                 * HIMPUNAN, não contagem. Com contagem, migrar uma frase e deixar
                 * outra entrar dava o mesmo número — e foi assim que 'Convidar em
                 * massa' pôde morar numa tela "verde".
                 */
                $palavras = array_map(
                    static fn (string $t): string => mb_strtolower($t, 'UTF-8'),
                    array_values(array_unique($c[0])),
                );

                sort($palavras);

                $prosa[$uri] = $palavras;
            }
        }
    } finally {
        app()->setLocale($localeOriginal);
    }

    /*
     * A contagem é parte da asserção: sem ela, um `actingAs` que não passa na
     * autorização devolve redirect em tudo, o loop não fiscaliza nada, e o guarda
     * fica verde olhando para nada.
     */
    expect($telasRendered)->toBeGreaterThanOrEqual(25, 'telas que QUEBRARAM (5xx) durante a varredura: '
        .($quebradas === [] ? 'nenhuma' : implode(' · ', $quebradas)));

    /*
     * RATCHET de himpunan, não contagem.
     *
     * Uma FRASE NOVA que nunca apareceu numa tela = vermelho. Isso é o alarme de
     * revert: se o `kit:update` devolver um arquivo com o literal PT cru, a frase
     * dele aparece na lista e nenhuma tela mapeada a conhecia antes. Contagem
     * serviria menos — um revert de `__('Email')` para `'E-mail'` numa tela que já
     * tinha 'E-mail' mapeado manteria o número igual, verde, com a tela quebrada.
     *
     * O que está dentro da lista é trabalho conhecido e aceito: prosa de ajuda e
     * texto de payload compartilhado (modal/busca global) que ainda não migrada.
     * Baixar é o progresso; quando uma tela ficar vazia, apague a chave dela.
     */
    /*
     * Medido em 2026-09-22 com as áreas 1–2, o grupo de navegação, os labels do
     * Shield e o seed English já aplicados. NÃO é meta: é o mapa do trabalho que
     * resta, por tela. Uma frase que só aparece AQUI é texto de payload compartilhado
     * (modal, busca global, filtro fechado) ou prosa de ajuda que ainda não tem
     * entrada no dicionário — não é necessariamente algo que o usuário está lendo
     * agora, e foi por isso que a checagem visual no navegador foi feita à parte.
     */
    $frasesJaConhecidas = [
        '/'                                => ['Multi-organização', 'Agentes de IA', 'Backups', 'Convite', 'Convites', 'E-mail', 'Nome', 'Organização', 'Organizações', 'Papéis', 'Permissões', 'Projeto', 'Trilhas', 'Usuário', 'Usuários', 'Versão'],
        'admin/agentes-ia/create'          => ['Modelo', 'Sistema', 'Versão'],
        'admin/configuracoes-da-aplicacao' => ['E-mail'],
        'admin/convites/create'            => ['Convite', 'E-mail', 'Organização', 'Papel'],
        'admin/meu-perfil'                 => ['E-mail', 'Senha'],
        'admin/shield/roles/create'        => ['Aceitar convite', 'Agentes de IA', 'Convite', 'Convites', 'Convites por situação', 'Organização', 'Papel', 'Permissões', 'Projeto', 'Projetos', 'Recusar convite', 'Situação', 'Usuário', 'Usuários', 'Usuários e acesso',
            // Os RÓTULOS de permissão não estão mais aqui. Antes este arquivo aceitava
            // 'Aceitar','Recusar','Desativar','Reativar' porque o Shield humanizava o
            // IDENTIFICADOR (`Str::headline('Desativar')`), e o nome em `permissions.name` é
            // identidade — comparado por `authorize()` e pelo banco, portanto inegível.
            // A exibição agora vem de `lang/<locale>/rotulo_permissao.php`
            // (`filament-shield.localization => true`) nas DUAS trilhas do resolvedor:
            // afixo (`desativar`) e permissão custom (`aceitar_convite`). Medido na tela em
            // `en`: Aceitar=0 Recusar=0 Desativar=0 Reativar=0, com Accept/Decline invitation
            // e Deactivate/Reactivate no lugar.
            'Dashboard dinâmico',
        ],
        'admin/users'                      => ['Ativo', 'Inativo'],
        'admin/users/create'               => ['Nome', 'Papel'],
        'app/email-verification/prompt'    => ['Convite', 'Sistema'],
        'app/meu-perfil'                   => ['E-mail', 'Senha'],
        'infra/backup-runs'                => ['Backups'],
        'infra/hub-de-infraestrutura'      => ['Backups', 'E-mail', 'Execuções de IA', 'Observabilidade', 'Pacote', 'Quando', 'Trilhas'],
        'infra/meu-perfil'                 => ['E-mail', 'Senha'],
    ];

    // Medido 2026-09-22 depois da rodada de PROSA (helperText/description das telas de
    // configuração e de criação): 13 telas com frase PT viraram 2. O que sobrou vem da
    // SAÍDA DE TERMINAL do kit (KitInfo.php, CustomizadorDaInstalacao), que essas telas
    // incorporam como resumo — e ela é afirmada em português por KitInfoTest e
    // CustomizadorDaInstalacaoTest, arquivos do upstream. Traduzir console é decisão
    // separada, não reparo de copy.

    $novas = [];

    foreach ($violacoes as $uri => $frases) {
        $saoNovas = array_values(array_diff($frases, $frasesJaConhecidas[$uri] ?? []));

        if ($saoNovas !== []) {
            $novas[] = $uri.' → '.implode(' | ', $saoNovas);

            foreach ($saoNovas as $pt) {
                $novas[] = '      '.($contexto[$uri][$pt] ?? '(sem janela)');
            }
        }
    }

    expect($novas)->toBe([], 'frase portuguesa NOVA chegou a uma tela ('.count($prosa)." telas têm prosa PT):\n".implode("\n", $novas));

    /*
     * Segunda esteira, e é a que fecha a lacuna da primeira.
     *
     * `$frasesJaConhecidas` só conhece quem já tem entrada em `lang/pt_BR.json` — ou
     * seja, mede com o catálogo que eu mesmo escrevi. Um literal português que nunca
     * foi catalogado é invisível para ela, e foi exatamente assim que três defeitos
     * reais passaram verdes nesta sessão: `'Vinculos'` (seção da ficha do usuário),
     * `'Convidar em massa'` (ação + heading da modal) e `$heading = 'Convites por
     * situação'` (rosca do dashboard). Este mapa aqui é medido NA TELA, palavra por
     * palavra, com um léxico que não depende do catálogo.
     *
     * Mesma semântica de ratchet: palavra nova numa tela mapeada = vermelho; tela nova
     * com palavra PT fora do mapa = vermelho. Migrar faz a lista encolher — e apagar a
     * chave vazia é o progresso registrado.
     *
     * Medido em 2026-09-23, depois da onda de prosa (116 literais + os casos que o
     * extrator não alcança: Blade, `"{$var} ..."`, chave de array que vira rótulo).
     *
     * Cada palavra aqui é TRABALHO CONHECIDO E ACEITO POR ENQUANTO, não ruído: as três
     * telas `meu-perfil` carregam a prosa do Breezy em português; `configuracoes-da-
     * aplicação` ainda tem o parágrafo de 2FA/recuperação; `admin/shield/roles/create`
     * mostra nomes de permissão que vêm do banco; `/` mostra 'organização'/'padrão'/
     * 'âmbar' de rótulo configurável e de dado. Palavra NOVA = vermelho.
     */
    $prosaJaConhecida = [
        '/'                                => ['organização', 'organizações', 'padrão', 'âmbar'],
        'admin/configuracoes-da-aplicacao' => ['e-mail'],
        'admin/convites/create'            => [],
        'admin/meu-perfil'                 => ['autenticação', 'definir', 'dá', 'e-mail', 'está', 'não', 'para', 'por', 'página', 'quem', 'senha', 'sessão', 'só', 'tem', 'um', 'uma', 'você'],
        'admin/shield/roles/create'        => ['aceitar', 'convite', 'convites', 'dinâmico', 'organização', 'papeis', 'recusar', 'usuario'],
        'admin/users/create'               => [],
        'app/email-verification/prompt'    => [],
        'app/meu-perfil'                   => ['autenticação', 'definir', 'dá', 'e-mail', 'está', 'não', 'para', 'por', 'página', 'quem', 'senha', 'sessão', 'só', 'tem', 'um', 'uma', 'você'],
        'infra/hub-de-infraestrutura'      => ['um'],
        'infra/meu-perfil'                 => ['autenticação', 'definir', 'dá', 'e-mail', 'está', 'não', 'para', 'por', 'página', 'quem', 'senha', 'sessão', 'só', 'tem', 'um', 'uma', 'você'],
    ];

    $novasPalavras = [];

    foreach ($prosa as $uri => $palavras) {
        $saoNovas = array_values(array_diff($palavras, $prosaJaConhecida[$uri] ?? []));

        if ($saoNovas !== []) {
            $novasPalavras[] = sprintf("        '%s' => [%s],", $uri, implode(', ', array_map(
                static fn (string $p): string => "'".$p."'",
                $saoNovas,
            )));
        }
    }

    expect($novasPalavras)->toBe([], "prosa portuguesa (léxico, sem depender do catálogo) chegou a novas telas/palavras.\n"
        ."Se for trabalho conhecido, cole as linhas abaixo no \$prosaJaConhecida deste arquivo.\n"
        .implode("\n", $novasPalavras));
});

it('control positif: o extrator enxerga o portugues visivel e ignora o payload', function (): void {
    /*
     * Sem este caso, o guarda verde pode significar "não leu nada" — que é o defeito
     * que `DOMDocument` acabou de causar aqui, e que `tests/Pest.php:1107` já
     * registrou nesta base ("46 casos verdes em cima de qualquer coisa").
     */
    $html = '<html><body>'
        .'<div wire:id="abc" x-data="{open:false}">Definir senha por e-mail</div>'
        .'<script>window.__s = {"label":"Organização","o":"Convite"};</script>'
        .'<template><span>Tooltip fechado: Papel</span></template>'
        .'<style>.x::after{content:"Situação"}</style>'
        .'</body></html>';

    $texto = textodeTela($html);

    expect($texto)->toContain('Definir senha por e-mail')
        ->and($texto)->not->toContain('Organização')
        ->and($texto)->not->toContain('Convite')
        ->and($texto)->not->toContain('Papel')
        ->and($texto)->not->toContain('Situação');
});

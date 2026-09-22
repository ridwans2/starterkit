<?php

/*
|--------------------------------------------------------------------------
| Guarda "label de painel já é inglês"
|--------------------------------------------------------------------------
| O resto da suíte prova que o PORTUGUÊS ainda funciona. Nada prova o
| contrário — e é exatamente isso que falta para o pedido "não pode ser
| sobrescrito pelo update".
|
| Se o upstream devolver um arquivo desta área (`git checkout <tag> -- …` é
| como o `kit:update` aplica), o getter volta a ser literal PT cru. Isso NÃO
| deixa vermelho nenhum por si só: a guarda de identidade byte-a-byte afirma PT,
| então ela passa ainda melhor. O sintoma é silencioso — a tela do usuário é que
| volta a falar português.
|
| Este caso é o alarme. Varre, por reflexão, os quatro getters de rótulo de TODA
| classe abaixo de `app/Filament` e falha se algum retornar literal sem passar
| pelo translator. Varredura, não lista: um resource novo que o kit publicar
| amanhã com `getTitle(): 'Coisa'` já cai aqui.
|
| A varredura vive dentro de uma closure chamada pelo `it()`, não no topo do
| arquivo: em Pest o topo roda na CARGA do arquivo, antes de a aplicação existir,
| e `app_path()` ali é `Call to undefined method Container::path()`.
*/

use App\Filament\Admin\Pages\ConfiguracoesDoKit;

/** Métodos de rótulo que pertencem à área migrada. */
$METODOS_DE_LABEL = ['getTitle', 'getNavigationLabel', 'getModelLabel', 'getPluralModelLabel'];

/**
 * Devolve [classes visitadas, violações "arquivo::método = literal"].
 *
 * @param  list<string>  $metodos
 * @return array{0: int, 1: list<string>}
 */
$varrer = function (array $metodos): array {
    $pasta      = app_path('Filament');
    $encontrado = [];
    $dilihat    = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS)) as $arquivo) {
        if ($arquivo->getExtension() !== 'php') {
            continue;
        }

        $caminho = str_replace('\\', '/', $arquivo->getPathname());
        $classe  = 'App\\'.substr(str_replace('/', '\\', $caminho), strlen($pasta) - strlen('Filament'), -4);

        // Traits vivem no mesmo diretório e não são classes: `class_exists` neles é false.
        if (! class_exists($classe)) {
            continue;
        }

        $reflexao = new ReflectionClass($classe);
        $linhas   = file($caminho);
        $dilihat++;

        foreach ($metodos as $metodo) {
            if (! $reflexao->hasMethod($metodo)) {
                continue;
            }

            $rm = $reflexao->getMethod($metodo);

            // Só o que a própria classe declara; herança alheia viraria linha fantasma.
            if ($rm->getDeclaringClass()->getName() !== $classe) {
                continue;
            }

            $corpo = implode('', array_slice($linhas, $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1));

            // `return 'Rótulo'` cru. `__(…)`, `config(…)` e `str(…)` não casam aqui.
            if (preg_match('/return\s+(?:\([^)]*\)\s*)?([\'"])((?:(?!\1).)*)\1\s*;/s', $corpo, $c)) {
                $encontrado[] = sprintf(
                    '%s::%s() (%s:%d) devolve "%s"',
                    $reflexao->getShortName(),
                    $metodo,
                    $arquivo->getFilename(),
                    $rm->getStartLine(),
                    $c[2],
                );
            }
        }
    }

    return [$dilihat, $encontrado];
};

it('não deixa nenhum rótulo de painel escapar do translator', function () use ($varrer, $METODOS_DE_LABEL): void {
    [$dilihat, $encontrado] = $varrer($METODOS_DE_LABEL);

    /*
     * A contagem é parte da asserção: sem ela, um caminho errado na varredura
     * produz `$encontrado === []` e o guarda fica verde sem olhar nada. É o
     * "46 casos verdes em cima de qualquer coisa" que `tests/Pest.php:1107` já
     * registrou nesta base.
     */
    expect($dilihat)->toBeGreaterThan(40)
        ->and($encontrado)->toBe([], "getters de rótulo abaixo ainda retornam literal cru.\n".implode("\n", $encontrado));
});

it('control positif: um getter migrado devolve inglês quando o idioma é inglês', function (): void {
    /*
     * Se o upstream devolver `ConfiguracoesDoKit.php`, o `__('…')` desaparece e
     * esta asserção cai. Na suíte o idioma está fixado em pt_BR, então trocar de
     * idioma aqui é o que distingue "código inglês + overlay PT" de "código PT".
     */
    app()->setLocale('en');

    try {
        expect(ConfiguracoesDoKit::getNavigationLabel())->toBe('Application settings');
    } finally {
        app()->setLocale((string) config('localization.suite', 'pt_BR'));
    }
});

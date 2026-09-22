<?php

/*
|--------------------------------------------------------------------------
| Guarda do idioma do projeto
|--------------------------------------------------------------------------
| Estes casos não provam que o app está bonito em inglês. Provam que a troca
| de idioma padrão CONTINUA DE PÉ depois de um `kit:update` — que é o risco
| real deste trabalho, porque quase todo arquivo que o kit entrega é oferecido
| de volta na versão portuguesa.
|
| `tests/Feature/` é pasta do projeto: o kit não a encosta (ver o cabeçalho de
| `ExemploTest.php`), então nem estes casos nem o provider que eles exercitam
| aparecem no diff do `kit:update`.
*/

use App\Providers\LocalizacaoProvider;
use App\Support\Formatos;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;

it('declara inglês como idioma padrão e português como segundo idioma', function (): void {
    expect(config('localization.idiomas'))->toBe(['en', 'id', 'pt_BR'])
        ->and(config('localization.suite'))->toBe('pt_BR');
});

it('mantém a suíte medindo o kit em português, não o idioma do projeto', function (): void {
    /*
     * O caso que importa. Sem o pino, ~90 arquivos de `tests/Kit` e
     * `tests/Tenancy` — que pertencem ao upstream e afirmam sobre o texto
     * português renderizado — cairiam vermelhos no dia em que os rótulos
     * passarem a depender de tradução, e a tentação seguinte seria "consertar"
     * o teste do upstream. O pino existe para isso nunca acontecer.
     */
    expect(app()->getLocale())->toBe('pt_BR');
});

it('registra o provider de localização na aplicação', function (): void {
    $providers = file_get_contents(base_path('bootstrap/providers.php'));

    expect($providers)->toContain(LocalizacaoProvider::class);
});

it('mostra o seletor de idioma quando o projeto aplica a sua lista', function (): void {
    // Estado do kit: um idioma só, seletor escondido. É o que
    // `tests/Kit/PacotesTierSTest.php` afirma, e o provider não pode contradizê-lo.
    expect(config('kit.idiomas'))->toBe(['pt_BR'])
        ->and(LanguageSwitch::make()->isVisibleInsidePanels())->toBeFalse();

    (new LocalizacaoProvider($this->app))->aplicar();

    expect(config('kit.idiomas'))->toBe(['en', 'id', 'pt_BR'])
        ->and(LanguageSwitch::make()->isVisibleInsidePanels())->toBeTrue();
});

it('não sobrescreve kit.idiomas durante os testes', function (): void {
    /*
     * O gate de `runningUnitTests()` é a única razão de o teste do upstream
     * acima continuar verde. Se alguém remover o gate, `register()` passa a
     * valer também na suíte e este caso acusa na hora.
     */
    (new LocalizacaoProvider($this->app))->register();

    expect(config('kit.idiomas'))->toBe(['pt_BR']);
});

it('troca a máscara de data junto com o idioma, e devolve a do kit em pt_BR', function (string $idioma, string $esperada): void {
    /*
     * A máscara É conteúdo de idioma: `22/09/2026 18:43` numa frase em inglês está
     * dizendo outra coisa do resto da tela. O que este caso trava é o contrato dos dois
     * lados — em `pt_BR` a máscara tem de continuar `d/m/Y H:i` EXATAMENTE como estava
     * escrita à mão em 25 pontos do código (é o que as telas afirmam hoje), e nos outros
     * dois idiomas ela tem de ser a ISO, não o padrão `M j, Y` do Filament.
     */
    $original = app()->getLocale();

    try {
        app()->setLocale($idioma);

        expect(Formatos::dataHora())->toBe($esperada)
            ->and(Formatos::data())->toBe(match ($idioma) {
                'pt_BR' => 'd/m/Y',
                default => 'Y-m-d',
            });
    } finally {
        app()->setLocale($original);
    }
})->with([
    'en'    => ['en', 'Y-m-d H:i'],
    'id'    => ['id', 'Y-m-d H:i'],
    'pt_BR' => ['pt_BR', 'd/m/Y H:i'],
]);

<?php

/*
|--------------------------------------------------------------------------
| Guarda do `i18n:extract`
|--------------------------------------------------------------------------
| O comando é o que faz o idioma sobreviver a `kit:update`. Um reescritor que
| escreve no offset errado não avisa: ele produz arquivos que ainda parecem
| PHP e só morrem no autoload — foi exatamente assim que a primeira versão
| deste comando escreveu `__('Home')` dentro de uma cláusula `use`.
|
| Por isso o caso não mede "rodou sem exceção". Ele escreve um fixture com as
| quatro formas que importam e compara o arquivo inteiro, byte por byte.
*/

use Illuminate\Support\Facades\Artisan;

$fixture = sys_get_temp_dir().'/i18n-extract-fixture.php';

afterEach(function () use ($fixture): void {
    if (is_file($fixture)) {
        unlink($fixture);
    }
});

it('aplica o inglês só nas três formas de UI, e não no resto do arquivo', function () use ($fixture): void {
    $original = <<<'PHP'
        <?php

        namespace App\Fixture;

        use App\Models\Convite;

        class AlgumaCoisa
        {
            public static function getNavigationLabel(): string
            {
                return 'Convites';
            }

            public function forma(): Section
            {
                return Section::make('x')->label('Organização')->description('Usuários');
            }

            protected function situacao(string $s): string
            {
                return match ($s) {
                    'Organização' => 'ok',
                    default => $s,
                };
            }

            /** Rótulos de array, como `Paineis::rotulos()`. */
            public function rotulos(): array
            {
                return ['tenants' => 'Organização'];
            }

            public function jaMigrado(): Section
            {
                return Section::make('x')->label(__('Organization'));
            }
        }
        PHP;

    file_put_contents($fixture, $original);

    expect(Artisan::call('i18n:extract', ['--path' => [$fixture]]))->toBe(0)
        ->and((string) file_get_contents($fixture))->toBe(<<<'PHP'
            <?php

            namespace App\Fixture;

            use App\Models\Convite;

            class AlgumaCoisa
            {
                public static function getNavigationLabel(): string
                {
                    return __('Invitations');
                }

                public function forma(): Section
                {
                    return Section::make('x')->label(__('Organization'))->description(__('Users'));
                }

                protected function situacao(string $s): string
                {
                    return match ($s) {
                        'Organização' => 'ok',
                        default => $s,
                    };
                }

                /** Rótulos de array, como `Paineis::rotulos()`. */
                public function rotulos(): array
                {
                    return ['tenants' => __('Organization')];
                }

                public function jaMigrado(): Section
                {
                    return Section::make('x')->label(__('Organization'));
                }
            }
            PHP);
});

it('não oferece nada quando o arquivo já está migrado (idempotência)', function () use ($fixture): void {
    file_put_contents($fixture, "<?php\n\nclass X\n{\n    public function f(): string\n    {\n        return __('Organization');\n    }\n}\n");

    $antes = (string) file_get_contents($fixture);

    expect(Artisan::call('i18n:extract', ['--path' => [$fixture], '--report' => true]))->toBe(0)
        ->and((string) file_get_contents($fixture))->toBe($antes);
});

it('para alto se o dicionário tiver o mesmo português para dois ingleses diferentes', function () use ($fixture): void {
    /*
     * Silencioso aqui é a pior saída possível: o comando escolheria um dos dois e
     * traduziria a tela inteira para o inglês errado. O caminho de saída é o
     * relatório vazio, e quem mantém o overlay é avisado.
     */
    $overlay          = base_path('lang/pt_BR.json');
    $conteudoOriginal = (string) file_get_contents($overlay);

    $dicionario                = json_decode($conteudoOriginal, true, 512, JSON_THROW_ON_ERROR);
    $dicionario['Duplicate B'] = 'Organização';

    file_put_contents($overlay, (string) json_encode($dicionario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    file_put_contents($fixture, "<?php\n\nclass X\n{\n    public static function getTitle(): string\n    {\n        return 'Organização';\n    }\n}\n");

    try {
        expect(Artisan::call('i18n:extract', ['--path' => [$fixture]]))->toBe(1)
            ->and((string) file_get_contents($fixture))->not->toContain('__(');
    } finally {
        file_put_contents($overlay, $conteudoOriginal);
    }
});

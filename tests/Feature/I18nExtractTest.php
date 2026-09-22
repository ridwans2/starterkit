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
            // Argumento de atributo é expressão CONSTANTE: `__()` aqui quebra o
            // compile. Foi assim que `app/Livewire/AssistenteChatWidget.php` morreu
            // uma vez — este fixture é o que impede a repetição.
            #[Validate('required', message: [
                'papel.required' => 'Papel',
            ])]
            public string $campo = '';

            public static function getNavigationLabel(): string
            {
                return 'Convites';
            }

            public function forma(): Section
            {
                return Section::make('Organização')
                    ->label('Organização')
                    ->description('Usuários');
            }

            public function nomesERotulos(): array
            {
                return [
                    // arg-1 de make() é NOME: a classe não está na allowlist.
                    TextColumn::make('Organização')->getLabel(),
                    // arg-1 de make() é rótulo.
                    StatPlus::make('Papéis', Role::query()->count()),
                    // 2º argumento de make() não é rótulo.
                    Tab::make('E-mail', 'x'),
                ];
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
                // Argumento de atributo é expressão CONSTANTE: `__()` aqui quebra o
                // compile. Foi assim que `app/Livewire/AssistenteChatWidget.php` morreu
                // uma vez — este fixture é o que impede a repetição.
                #[Validate('required', message: [
                    'papel.required' => 'Papel',
                ])]
                public string $campo = '';

                public static function getNavigationLabel(): string
                {
                    return __('Invitations');
                }

                public function forma(): Section
                {
                    return Section::make(__('Organization'))
                        ->label(__('Organization'))
                        ->description(__('Users'));
                }

                public function nomesERotulos(): array
                {
                    return [
                        // arg-1 de make() é NOME: a classe não está na allowlist.
                        TextColumn::make('Organização')->getLabel(),
                        // arg-1 de make() é rótulo.
                        StatPlus::make(__('Roles'), Role::query()->count()),
                        // 2º argumento de make() não é rótulo.
                        Tab::make(__('Email'), 'x'),
                    ];
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

it('dobra o acento: o inglês volta mesmo quando o PT foi escrito sem acento', function () use ($fixture): void {
    /*
     * Caso real, medido nesta base: `ConfiguracoesDoKit` carregava
     * `'Ja configurada — em branco mantem'` — português sem acento, digitado à mão.
     * O dicionário só conhecia `'Já configurada — em branco mantém'`, então nem o
     * extrator nem a guarda de tela viam o problema, e a tela em inglês mostrava PT.
     *
     * O que está em jogo é durabilidade, não ortografia: quando um `kit:update`
     * devolver um arquivo nesse estilo, a recuperação automática tem de funcionar do
     * mesmo jeito, sem depender de alguém digitar o acento certo.
     */
    file_put_contents($fixture, "<?php\n\nclass X\n{\n    public static function getTitle(): string\n    {\n        return 'Ja configurada — em branco mantem';\n    }\n}\n");

    expect(Artisan::call('i18n:extract', ['--path' => [$fixture]]))->toBe(0)
        ->and((string) file_get_contents($fixture))->toContain("__('Already configured — blank keeps it')");
});

it('pulih sendiri setelah `kit:update` mengembalikan berkas ke format lama', function () use ($fixture): void {
    /*
     * `KitUpdate::CAMINHOS_DO_KIT` mengirim DIREKTORI (`app/Filament`, `app/Notifications`,
     * `resources/views/livewire`), jadi hampir semua berkas yang dimigrasi bisa balik
     * ke Portugal dalam satu update. Yang menahan bahasa Inggris bukan kekebalan berkas
     * — adalah kamus. Medido em dua kelas yang berbeda:
     *
     *  - `'Somente inativos'`: Portugal tanpa aksen, jadi tidak tertangkap oleh filter
     *    léxico mana pun yang mengandalkan aksen;
     *  - `'Ja configurada — em branco mantem'': Portugal dengan tanda baca utuh tapi
     *    aksen dibuang tangan oleh penulisnya.
     *
     * Kalau suatu hari kunci ini keluar dari `lang/pt_BR.json`, kasus ini merah — dan itu
     * yang diinginkan: yang diam bukan ekstraktornya, tapi kamusnya.
     */
    file_put_contents($fixture, "<?php\n\nclass Revert\n{\n    public static function getTitle(): string\n    {\n        return 'Somente inativos';\n    }\n\n    public static function getSubheading(): string\n    {\n        return 'Ja configurada — em branco mantem';\n    }\n}\n");

    expect(Artisan::call('i18n:extract', ['--path' => [$fixture]]))->toBe(0)
        ->and((string) file_get_contents($fixture))
        ->toContain("__('Only inactive')")
        ->toContain("__('Already configured — blank keeps it')");
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

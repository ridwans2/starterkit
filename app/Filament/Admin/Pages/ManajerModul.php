<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\ExigePermissaoDaTela;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nwidart\Modules\Facades\Module;

/**
 * Module Manager: satu tela /admin/manajer-modul untuk melihat modul yang ada di disk.
 *
 * Sumber data adalah `nwidart/laravel-modules` (diretori `Modules/` + `modules_statuses.json`),
 * bukan database — oleh itu tabelnya custom records (`Table::records()`), pola yang sama dengan
 * `ssbityukov/filament-command-center` yang sudah terpasang: Filament 5 tidak menyaring
 * pencarian/pengurutan sendiri di luar query Eloquent, jadi pencarian dan urutan diterapkan
 * manual di dalam closure `records()`.
 *
 * ## Autorisasi
 *
 * `ExigePermissaoDaTela` — permission `View:ManajerModul` lahir dari `ShieldPermissionsSeeder`
 * dan sampai ke papel `admin` lewat `PapeisSeeder`, seperti `ConfiguracoesDoKit`. Keempat aksi
 * tulis (create/enable/disable/delete) berdiri di atas permission yang sama — semuanya sudah
 * halaman admin-only, dan hapus diberi rem ketik-nama karena menghapus folder di disk.
 */
class ManajerModul extends Page implements HasTable
{
    use ExigePermissaoDaTela;
    use InteractsWithTable;

    protected string $view = 'filament.pages.manajer-modul';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    public function getTitle(): string
    {
        return __('Module Manager');
    }

    public static function getNavigationLabel(): string
    {
        return __('Module Manager');
    }

    /**
     * Aksi header: module:make lewat form, dengan nama tervalidasi pola — nama ini yang nanti
     * dipakai sebagai argumen command dan sebagai nama namespace, jadi tidak ada kompromi.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatModul')
                ->label(__('New module'))
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__('New module'))
                ->modalSubmitActionLabel(__('Create'))
                ->schema([
                    TextInput::make('nama')
                        ->label(__('Module name'))
                        ->required()
                        ->maxLength(50)
                        ->rule('regex:/^[A-Za-z][A-Za-z0-9]*$/')
                        ->helperText(__('Letters and digits only, starting with a letter — e.g. Invoice.'))
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && Module::has((string) $value)) {
                                    $fail(__('A module named :name already exists.', ['name' => $value]));
                                }
                            },
                        ]),
                ])
                ->action(function (array $data): void {
                    $nama = (string) $data['nama'];

                    // Gerbang kedua, di luar form: action bisa dipanggil lewat Livewire RPC
                    // tanpa payload form pernah lolos ke validator.
                    abort_unless(preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $nama) === 1, 400);
                    abort_if(Module::has($nama), 409);

                    // module:make mengakhiri dirinya dengan `composer dump-autoload` lewat
                    // Process — di Windows itu lebih dari 30s dan HTTP mati di tengah jalan
                    // (modul jadi, respons tidak). Ukur 2026-09-23: timeout tepat di situ.
                    @set_time_limit(180);
                    Artisan::call('module:make', ['name' => [$nama]]);
                    Module::resetModules();

                    Notification::make()
                        ->title(__('Module :name created.', ['name' => $nama]))
                        ->success()
                        ->send();

                    $this->flushCachedTableRecords();
                }),
        ];
    }

    /**
     * Baris tabel, satu per modul di disk.
     *
     * Pencarian dan pengurutan dikerjakan di sini karena `records()` tidak punya query untuk
     * disaring Filament.
     *
     * @return array<int, array<string, mixed>>
     */
    public function records(?string $search, ?string $sortColumn, ?string $sortDirection): array
    {
        $baris = [];

        foreach (Module::all() as $modul) {
            $baris[] = [
                'id'        => $modul->getName(),
                'nama'      => $modul->getName(),
                'status'    => $modul->isEnabled() ? 'active' : 'inactive',
                'versi'     => (string) ($modul->get('version') ?: $modul->getComposerAttr('version', '')),
                'deskripsi' => (string) ($modul->get('description') ?: $modul->getComposerAttr('description', '')),
                'penyedia'  => count((array) ($modul->get('providers', []) ?? [])),
                'path'      => Str::after($modul->getPath(), base_path().DIRECTORY_SEPARATOR),
            ];
        }

        if (filled($search)) {
            $term  = Str::lower($search);
            $baris = array_filter(
                $baris,
                fn (array $b): bool => Str::contains(mb_strtolower($b['nama'].$b['deskripsi'].$b['versi']), $term)
            );
        }

        $sort      = $sortColumn ?? '';
        $direction = $sortDirection ?? 'asc';

        if ($sort !== '') {
            usort($baris, function (array $a, array $b) use ($sort, $direction): int {
                $hasil = $a[$sort] <=> $b[$sort];

                return $direction === 'desc' ? -$hasil : $hasil;
            });
        }

        return array_values($baris);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection): array => $this->records($search, $sortColumn, $sortDirection))
            ->columns([
                TextColumn::make('nama')
                    ->label(__('Module'))
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (array $record): string => $record['path'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('Active') : __('Inactive'))
                    ->sortable(),
                TextColumn::make('versi')
                    ->label(__('Version'))
                    ->placeholder('—'),
                TextColumn::make('deskripsi')
                    ->label(__('Description'))
                    ->limit(60)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('penyedia')
                    ->label(__('Providers'))
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('nama')
            ->paginated(false)
            ->recordActions([
                Action::make('detail')
                    ->label(__('Details'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->slideOver()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->schema(fn (array $record): array => $this->skemaDetail($record)),

                Action::make('ubahStatus')
                    ->label(fn (array $record): string => $record['status'] === 'active' ? __('Disable') : __('Enable'))
                    ->icon(fn (array $record): Heroicon => $record['status'] === 'active' ? Heroicon::OutlinedPower : Heroicon::OutlinedPlay)
                    ->color(fn (array $record): string => $record['status'] === 'active' ? 'warning' : 'success')
                    ->action(function (array $record): void {
                        $this->ubahStatus($record['nama']);
                    }),

                Action::make('hapus')
                    ->label(__('Delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->modalHeading(__('Delete module'))
                    ->modalDescription(fn (array $record): string => __('The folder :path is removed from disk, together with its status entry. This cannot be undone.', [
                        'path' => $record['path'],
                    ]))
                    ->modalSubmitActionLabel(__('Delete'))
                    // Penghapusan permanen di disk: ketik-nama adalah satu-satunya rem, karena
                    // konfirmasi sekali-klik saja tidak cukup untuk operasi yang tak bisa di-undo.
                    ->schema([
                        TextInput::make('konfirmasi')
                            ->label(__('Type the module name to confirm'))
                            ->required()
                            ->rules([
                                fn (array $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (mb_strtolower((string) $value) !== mb_strtolower($record['nama'])) {
                                        $fail(__('The confirmation does not match the module name.'));
                                    }
                                },
                            ]),
                    ])
                    ->action(function (array $data, array $record): void {
                        $this->hapusModul((string) $record['nama'], (string) $data['konfirmasi']);
                    }),
            ])
            ->emptyStateHeading(__('No modules found'))
            ->emptyStateDescription(__('Create one with "New module", or run php artisan module:make.'))
            ->emptyStateIcon(Heroicon::OutlinedFolder);
    }

    /**
     * Ficha do modulo no slide-over.
     *
     * `state()` sempre em closure com `$record['kunci']`: record di sini adalah array dari
     * `records()`, bukan Model, dan resolução de state padrão do Entry tidak diandalkan untuk itu.
     * Daftar provider dibaca ulang dari disk (`module.json`) karena baris tabel hanya menyimpan
     * jumlahnya.
     *
     * @param  array<string, mixed>  $record
     * @return array<Component>
     */
    protected function skemaDetail(array $record): array
    {
        $modul = Module::find($record['nama']);

        return [
            Section::make(__('Module'))
                ->columns(2)
                ->schema([
                    TextEntry::make('nama')
                        ->label(__('Module'))
                        ->state(fn (): string => $record['nama']),
                    TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->color($record['status'] === 'active' ? 'success' : 'gray')
                        ->state($record['status'])
                        ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('Active') : __('Inactive')),
                    TextEntry::make('versi')
                        ->label(__('Version'))
                        ->state(fn (): string => (string) $record['versi'])
                        ->placeholder('—'),
                    TextEntry::make('penyedia')
                        ->label(__('Providers'))
                        ->state(fn (): string => (string) $record['penyedia']),
                    TextEntry::make('deskripsi')
                        ->label(__('Description'))
                        ->state(fn (): string => (string) $record['deskripsi'])
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('path')
                        ->label(__('Path'))
                        ->state(fn (): string => (string) $record['path'])
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->columnSpanFull(),
                    TextEntry::make('provider_list')
                        ->label(__('Provider classes'))
                        ->state(fn (): ?string => $modul === null ? null : implode(', ', (array) $modul->get('providers', [])))
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(filled($modul?->get('providers'))),
                ]),
        ];
    }

    /**
     * Enable/disable lewat command resmi — bukan `$module->enable()` langsung — karena command
     * ikut menyinkronkan `modules_statuses.json` dan cache manifest seperti yang dilihat pengoper
     * lewat CLI.
     */
    protected function ubahStatus(string $nama): void
    {
        $modul = Module::find($nama);

        abort_unless($modul !== null, 404);

        $mengaktifkan = ! $modul->isEnabled();
        Artisan::call($mengaktifkan ? 'module:enable' : 'module:disable', ['module' => [$nama]]);

        Notification::make()
            ->title($mengaktifkan
                ? __('Module :name enabled.', ['name' => $nama])
                : __('Module :name disabled.', ['name' => $nama]))
            ->success()
            ->send();

        // Record array di-resolve (dan di-cache) SEBELUM aksi jalan — tanpa flush ini render
        // sesudahnya masih menampilkan status lama.
        $this->flushCachedTableRecords();
    }

    /**
     * Hapus permanen: folder modul di disk ikut hilang, tidak ada undo.
     *
     * Kecocokan konfirmasi diperiksa ulang DI SINI, bukan hanya di rule form — Livewire RPC bisa
     * mengirim payload yang tidak pernah melewati validator layar, dan perintah delete tidak
     * punya kesempatan kedua.
     */
    protected function hapusModul(string $nama, string $konfirmasi): void
    {
        if (mb_strtolower($konfirmasi) !== mb_strtolower($nama)) {
            throw ValidationException::withMessages([
                'data.konfirmasi' => __('The confirmation does not match the module name.'),
            ]);
        }

        abort_unless(preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $nama) === 1, 400);

        $modul = Module::find($nama);

        abort_unless($modul !== null, 404);

        Artisan::call('module:delete', ['module' => [$nama], '--force' => true]);
        Module::resetModules();

        Notification::make()
            ->title(__('Module :name deleted.', ['name' => $nama]))
            ->success()
            ->send();

        $this->flushCachedTableRecords();
    }
}

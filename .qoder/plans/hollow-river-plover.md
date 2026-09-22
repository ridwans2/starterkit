# English-first: mengganti bahasa default dari pt-BR ke English, tahan update hulu

> **ATURAN KERJA (permintaan user, 2026-09-22):** setiap fase **WAJIB BERHENTI** setelah selesai.
> Jangan melangkah ke fase berikutnya sebelum user melakukan cek manual dan menyetujuinya.
> Update kolom status di tabel ini di tiap akhir fase.

## Daftar pengerjaan per fase

| no | tasks | status (selesai / belum) |
|---|---|---|
| 1 | Fase 0 — Spike API vendor (4 pertanyaan dijawab dengan eksperimen: kunci JSON bertitik, knob format tanggal Filament v5, rambat `setLocale` ke Carbon/Number, `->label()` auto-translate?) | ✅ |
| 2 | Fase 0 — Temuan `translateLabel()` global → Fase 3 jadi penggantian isi literal, bukan bungkus `__()` | ✅ |
| 3 | Fase 0 — `composer install` (vendor belum ada) | ✅ |
| 4 | Fase 1 — `config/localization.php` (idiomas, suite, formatos) | ✅ |
| 5 | Fase 1 — `app/Providers/LocalizacaoProvider.php` + registrasi di `bootstrap/providers.php` | ✅ |
| 6 | Fase 1 — Guard `tests/Feature/IdiomaDoProjetoTest.php` (5 kasus, termasuk pin locale suite) | ✅ |
| 7 | Fase 2 — Migration rename 4 tabel `2099_01_01_000001_*` (ber-guard `hasTable`) | ✅ |
| 8 | Fase 2 — `$table` eksplisit di 4 model (`Convite` & `Projeto` tadinya ikut konvensi nama class) | ✅ |
| 9 | Fase 2 — Tulis ulang 7 migration historis ke nama tabel English (dipaksa PHPStan/Larastan) | ✅ |
| 10 | Fase 2 — Guard `tests/Feature/IdentificadoresDoBancoTest.php` (9 kasus) | ✅ |
| 11 | Fase 2 — Sinkron 21 rujukan nama tabel di 5 file test hulu + 2 baris README | ✅ |
| 12 | Verifikasi terukur: Kit 2.356 · Tenancy 417 · Feature 16 · pint · filacheck 17/17 · phpstan 0 error | ✅ |
| 13 | Verifikasi engine MySQL — **SELESAI, dua jalur diukur di MySQL 26.7.0 nyata** | ✅ |
| 14 | `kit:tenancy`: **ON** terukur di MySQL nyata (`migrate:fresh --seed` → nol tabel PT + `team_id` ada). **OFF** diterima tidak diuji — keputusan user 2026-09-22, alasannya di bawah | ✅ |
| 15 | Fase 3 — **`momin-alzaraa/localization` TIDAK ADA** (Packagist 404, GitHub vendor 0). Mesin = scanner pihak ketiga (`gettext/php-scanner` atau `php-translation/extractor`) untuk penemuan + rewrite milik kita. Spike = pasang scanner, ukur di SATU direktori | belum |
| 16 | Fase 3 — Properti statis rótulo → getter: **28 situs di 14 file** (bukan ±24), guard baru 43 kasus | ✅ |
| 17 | Fase 3 — Ekstraksi literal PT → English (terukur: **1453 kemunculan / 1278 distinct / 163 file**, bukan 890/812/129), unit = kalimat utuh, 8–10 commit per area | belum — **area 1 selesai: 29 getter label (lihat HASIL BARIS 29)** |
| 18 | Fase 3 — 181 string interpolatif ketemu oleh tokenizer (`:app`) + node teks Blade | belum |
| 19 | Fase 4 — `tests/Feature/NenhumTextoPortuguesNoCodigoTest.php` hard-fail (tokenizer + kontrol positif) | belum |
| 20 | Fase 5 — 28 masker tanggal → knob global `defaultDateDisplayFormat()`, `Number::useLocale()` | belum |
| 21 | Fase 5 — Label tenancy English (`KIT_TENANCY_LABEL*` di `.env` → Organization/Organizations), `lang/vendor/*/en/` untuk 3 paket PT-only, buka `idiomas` → switcher muncul | sebagian — switcher ✅ 3 locale, label tenancy ✅, fallback `lang/vendor/*/en/` **belum** |
| 22 | Fase 5 — `.env`/`.env.example` `APP_LOCALE=en`; perbaikan timezone `config/app.php:94` (commit sendiri) | **`.env`+`.env.example` ✅** (APP_LOCALE=en, APP_FAKER_LOCALE=en_US) · timezone `config/app.php:94` **belum** |
| 23 | Fase 6 — `.ai/rules/idioma.md` + baris indeks, runbook dua bahasa `docs/en`+`docs/pt` | belum |
| 24 | Fase 6 — Uji simulasi revert jahat (restore file hulu → extractor pulihkan → guard hijau) | belum |
| 25 | Keputusan terbuka: apakah Fase 7 (kolom `nome`/`painel`, rename class, slug URL) masuk scope | **TERKUNCI 2026-09-22: YA, penuh sampai kolom dan class** |
| 26 | Keputusan terbuka: data demo/seed English berarti menyunting `database/{seeders,factories}` milik hulu — lanjut atau batal? | **TERKUNCI 2026-09-22: YA, seed ikut English** |
| 27 | Commit batas fase 0–2 — sudah dilakukan user (`560dd0c`) | ✅ |
| 28 | Commit pembersihan: 60 `.raja/temp/legado/**` keluar dari indeks + `/.raja/` di `.gitignore` (staged, menunggu perintah) | belum |
| 29 | **Gancho global `translateLabel()`** di `LocalizacaoProvider::terjemahkanLabelDasar()` (7 kelas dasar) + guard baru 6 kasus | ✅ |
| 30 | Locale ketiga `id` masuk `config/localization.php` (`idiomas`, `formatos`) + `lang/id.json` (20 kunci) | ✅ |
| 31 | Area 1 English: 29 getter label + 20 pasangan EN→PT/ID di overlay; `.env` `APP_LOCALE=en` | ✅ |
| 32 | Belum terverifikasi: `getSubheading()`/`getDescription()` widget (3 literal) — status "hidup" di v5 belum diukur, sengaja tidak disentuh | belum |
| 33 | Belum terverifikasi: `composer test:kit` penuh di atas area 1 (oracle byte-identity) — jalankan sebelum buka area 2 | belum — **dijalankan, hasil di bawah** |
| 34 | Alarm anti-revert area 1: `tests/Feature/LabelPainelSudahInggrisTest.php` (scan 4 getters por refleksi + kontrol positif + penghitung kelas yang dilihat) | ✅ |
| 35 | Celah yang diketahui dari alarm #34: scan hanya membaca **method**; file yang di-revert sampai ke bentuk **properti statis** lolos dari scan (dipegang `LabelPainelLewatGetterTest`, 43 kasus). Medido: `git checkout HEAD -- ConfiguracoesDoKit.php` → kasus scan hijau, kontrol positif MERAH | belum diutup |


## Context

Repo ini klon bersih (nol commit lokal — 907 commit semuanya `gsferro`/dependabot) dari hulu
`gsferro/filament-starter-kit-easy` v0.38.0 (`config/kit.php:23`). Seluruh UI berbahasa Portugis
**bukan lewat layer terjemahan**: hanya ~11 `__()` di seluruh `app/`. Sisanya literal PT hardcoded,
dan proyek ini sendiri mendeklarasikannya sebagai pekerjaan yang belum dikerjakan
(`config/kit.php:563-568`: *"Internacionalizar o kit é trabalho declarado e ainda não feito"*;
`app/Providers/Concerns/ConfiguraFilamentGlobal.php:166-168`: *"Ligar `en` hoje troca metade da tela"*).

Masalah utamanya bukan menerjemahkan, melainkan **kontrak update**. Hulu menyebarkan upgrade lewat
`php artisan kit:update` (`app/Console/Commands/KitUpdate.php`) yang memfilter diff dengan
`private const CAMINHOS_DO_KIT` (~186 entri) dan menerapkannya dengan
`git checkout <tag> -- <path>` (`KitUpdate.php:887-893`) — **penggantian berkas menyeluruh, tanpa
three-way merge**. Hampir semua lokasi string/identifier PT ada di dalam daftar itu.

**Fakta yang menyelamatkan**: diff dihitung antar dua tag hulu (`KitUpdate.php:64-70`), jadi
**berkas yang hanya ada di proyek kita tidak pernah muncul di diff** = imun secara struktural.

### Tiga koreksi terhadap asumsi awal (terverifikasi)

| Asumsi | Kenyataan |
| --- | --- |
| Laravel 12 + Filament v4 | **Laravel 13.32.0 + Filament 5.8.2** (`composer.lock:2841,4950`; PHP 8.4). Semua API Filament harus dijawab terhadap v5, bukan v4. |
| ~617 literal PT | **812 literal PT berbeda, 890 kemunculan, 129 file** (dihitung dengan tokenizer, bukan regex). 259 di antaranya (32%) berakhiran `.`/`!`/`?` — ini material, lihat Fase 0. |
| `vendor/` tersedia | **`vendor/` belum terpasang di checkout ini.** Empat pertanyaan internal framework belum bisa dijawab dari kode → masuk Fase 0. Konsekuensi praktis: langkah 0 semua verifikasi adalah `composer install`. |

### Keputusan user (sudah terkunci)

Kedalaman menyeluruh (label, teks, tabel, kolom, class, namespace, slug) · upgrade via
`git merge` + guard test · pt-BR dipertahankan sebagai locale · data demo ikut English ·
mekanisme tahan-update = **re-writer idempotent** · kontrak publik ikut English + migrasi data ·
rollout bertahap · **mesin ekstraksi = plugin `momin-alzaraa/localization`** (5.x, sudah di-shortlist
wiki proyek sendiri di `wikis/pacotes-ranking.md:310` sebagai "prasyarat nyata item 6").
Menambah dependensi ini = persetujuan eksplisit yang diminta `agents.md` ("Do not change the
application's dependencies without approval").

## Keputusan desain yang menentukan semuanya: English adalah sumber literal

**A**: kode berisi `->label(__('Organization'))`, PT jadi overlay yang dihasilkan di
`lang/pt_BR.json` (`"Organization": "Organização"`).
**B**: kode berisi `->label(__('Organização'))`, English yang jadi overlay.

**Pilih A.** Tiga alasan spesifik repo ini:

1. **Hanya A yang membuat "tertimpa hulu" terdeteksi.** Hulu akan terus mengirim PT. Under B, file
   yang di-revert mengembalikan literal PT *sebagai kunci* — tidak bisa dibedakan dari kode yang
   benar, dan scan statis apa pun jadi bisu. Under A, "tidak ada string PT di `app/**`" adalah
   invarian mekanis ber-signal tinggi. Ini persis alarm yang diminta user.
2. **Ini doktrin proyek itu sendiri**: `config/kit.php:342-346` sudah punya seksi *"Código em inglês,
   interface em português"*, diulang di `wikis/arquitetura.md:208`. A memperluas doktrin itu dari
   identifier ke copy; B menentangnya.
3. **A memenuhi permintaan sesuai teksnya** — pengalaman default English tanpa butuh berkas
   terjemahan ada sama sekali.

Biaya yang tidak saya sembunyikan: ~800 entri masuk `lang/pt_BR.json` yang tidak pernah ditulis
hulu, dan tiap perubahan label hulu = konflik. Biaya itu identik under B; A hanya membuatnya
terlihat.

Layout (semua imun, `lang/pt_BR.json` dan `lang/pt_BR/*` berada di luar `CAMINHOS_DO_KIT`):

```
lang/pt_BR.json                    # sudah ada (33 kunci EN→PT); jadi overlay PT
lang/en/                           # SENGAJA TIDAK DIBUAT — English adalah sumber
config/localization.php            # BARU: formats + locale suite
app/Support/IdiomasDoPainel.php    # BARU: pemilik daftar locale
app/Console/Commands/I18nExtract.php  # BARU: codemod cadangan + --report
app/Providers/LocalizacaoProvider.php # BARU: pin locale test + set kit.idiomas
```

Jangan buat `lang/en/**`: itu peta identitas (`'Organization' => 'Organization'`), tidak bisa
disunting, dan jadi pemilik kedua bagi teks yang sudah hidup di kode. Jangan taruh string plugin di
`lang/pt_BR.json` — konvensi repo untuk copy plugin adalah `lang/vendor/<pacote>/<locale>/`
(`KitUpdate.php:161-166` mencontohkannya untuk `filament-maillog`).

## Arsitektur kekebalan: tiga tingkat

1. **Imun struktural** — berkas yang hanya kita punya: `lang/pt_BR.json`, `config/localization.php`,
   `app/Providers/LocalizacaoProvider.php`, `app/Support/IdiomasDoPainel.php`, `app/Console/Commands/I18nExtract.php`,
   `bootstrap/providers.php`, `routes/web.php`, `database/migrations/2099_*`, `tests/Feature/**`,
   `tests/Unit/**`, `.env`, `docs/**`, `.github/**`, `composer.json`.
2. **Sembuh-diri** — berkas milik hulu yang tersentuh: `app/Filament/**`, `app/Support/**`,
   `app/Notifications/**`, `app/Providers/**`, 7 file model, `config/kit.php`, `.env.example`,
   `phpunit.xml`, `resources/views/{auth,errors,filament,livewire,svg}/**`. Setelah merge:
   `php artisan i18n:extract` (atau plugin) meng-aplikasi ulang; guard test membuktikan tuntas.
3. **Manual** — hanya untuk fitur baru hulu yang membawa string/identifier baru: guard mencetak
   daftar persisnya, kita isi overlay-nya.

---

## Fase 0 — Spike + fondasi — **SELESAI, terverifikasi di vendor**

`composer install` dijalankan (vendor memang belum ada). Empat pertanyaan dijawab dengan
eksperimen nyata, bukan asumsi:

| # | Pertanyaan | Jawaban terukur |
| --- | --- | --- |
| 1 | Kunci JSON berakhiran titik resolve? | **YA.** `__('Snapshot data was updated from GitHub.')` → `"Os dados foram atualizados a partir do GitHub."` di pt_BR, dan tetap dirinya sendiri di `en`. 4 dari 33 kunci `lang/pt_BR.json` yang sudah di-commit maintainer memang berakhir dengan titik. **Cabang fallback tidak diperlukan.** |
| 2 | Ada knob format tanggal global di Filament 5.8.2? | **YA.** `Filament\Support\Concerns\HasDefaultDataFormattingSettings` memberi `Table::defaultDateDisplayFormat()` / `defaultDateTimeDisplayFormat()` (default vendor `'M j, Y'`), dan `Table::date()/dateTime()` memakai getter itu saat argumennya `null`. Kit **sudah** punya `Table::configureUsing(...)` global (`ConfiguraFilamentGlobal.php:92`) → 28 titik hardcoded jadi **dihapus**, bukan ditulis ulang. |
| 3 | `setLocale()` merambat ke Carbon dan `Number`? | **Carbon YA** (Laravel sinkron di `Application::setLocale` — `translatedFormat('l')` berubah `segunda-feira` → `Monday` hanya dengan `setLocale`). **Number TIDAK** — `Number::format()` tetap `1,234,567.89` di pt_BR sampai `Number::useLocale('pt_BR')` dipanggil eksplisit (baru kemudian `1.234.567,89`). Jadi Fase 5 hanya butuh `Number::useLocale`, dan itu **perubahan perilaku yang harus di-commit sendiri** ( exporter & notifikasi jadi memakai pemisah PT). |
| 4 | `->label()` menerjemahkan string biasa? | **TIDAK** — tapi Filament v5 punya **`->translateLabel()`**, dan ia ada di semua permukaan UI: `Schemas\Components\Component`, `Tables\Columns\Column`, `Columns\ColumnGroup`, `Summarizers`, `Tables\Filters\BaseFilter`, `Actions`, query-builder constraints. |

### Temuan yang mengubah Fase 3 (lebih murah)

`ComponentManager::configure()` (`vendor/filament/support/src/Components/ComponentManager.php:105-106`)
menelusuri `[...array_reverse(class_parents($component)), $componentClass]` — **registrasi di kelas
dasar berlaku untuk semua subclass**. Terbukti empiris, satu registrasi global per kelas dasar:

```
antes                 ->label('Repository')  → "Repository"
Component::configureUsing(fn($c) => $c->translateLabel())
depois   schema/column/action/filter          → "Repositório"   (via lang/pt_BR.json)
```

Konsekuensi: **~300 call site Filament tidak perlu dibungkus `__()` sama sekali.** Codemod Fase 3
turun dari "operasi bedah sintaks" jadi **penggantian isi literal** (teks PT di dalam tanda petik →
teks English), yang:

- idempotent dan trivial untuk re-apply setelah merge hulu;
- menghasilkan diff satu-baris-per-label, bukan `->label('X')` → `->label(__('X'))` → konflik
  lebih kecil dan `rerere` lebih sering auto-resolve;
- **otomatis berlaku untuk label baru yang dikirim upstream nanti** selama string-nya English, dan
  mengembalikan string itu apa adanya kalau kuncinya belum ada (fallback aman, sudah terbukti di #1).

Surface yang **tetap** butuh `__()` eksplisit karena bukan komponen Filament: badan notifikasi,
array (`Paineis::rotulos()`), `match` arms, `enum getLabel()` di `app/Support/*`, dan Blade.
Dan `translateLabel()` global **tidak** menyentuh properti statis (`$title = '…'`) — itu tetap
±24 refactor getter manual.

Risiko yang sudah dipikirkan: label internal Filament/paket pihak ketiga yang sudah terjemahan akan
diterjemahkan dua kali. `__()` pada string yang bukan kunci mengembalikan dirinya sendiri, dan
string yang mengandung `.` pun sudah terbukti aman (#1), jadi efeknya nol — tapi ini wajib
dikonfirmasi oleh `composer test:kit` saat diaktifkan, bukan dipercaya.

### Status implementasi Fase 0–1

Sudah dibuat dan **berjalan** (semuanya imun — berkas yang tidak ada di pohon hulu):

- `config/localization.php` — `idiomas: ['en','pt_BR']`, `suite: 'pt_BR'`, `formatos.{en,pt_BR}`.
- `app/Providers/LocalizacaoProvider.php` — `aplicar()` menyalin `localization.idiomas` ke
  `kit.idiomas` di produksi; di dalam test ia just pin locale ke `pt_BR` dan **tidak** menyentuh
  `kit.idiomas` (diblokir `tests/Kit/PacotesTierSTest.php:153-157`).
- `bootstrap/providers.php` — provider terdaftar terakhir (disengaja; lihat komentar).
- `tests/Feature/IdiomaDoProjetoTest.php` — 5 guard, **semua hijau**.
- `.env` dibuat dari `.env.example` + `key:generate` (tanpa itu `ExemploTest` bawaan sudah merah
  karena `MissingAppKeyException` — kondisi awal repo, bukan regresi).
- `vendor/bin/pint --dirty` → bersih.

Catatan: rencana menyebut `app/Support/IdiomasDoPainel.php`; kelas itu **tidak dibuat** — nilainya
cukup hidup di `config/localization.php` dan provider menyalinnya. Satu pemilik, nol kelas tambahan.

Verifikasi sisa gerbang Fase 1: `php artisan test --testsuite=Unit,Kit,Tenancy --compact` hijau
**tanpa satu baris pun di `tests/Kit/**` diubah**.

### HASIL GERBANG FASE 1 (dijalankan, 2026-09-22)

`php artisan test --testsuite=Unit,Kit,Tenancy` → **2773 passed, 1 failed** (10.798 asersi, 21 menit),
dan kegagalan itu **bukan** regresi bahasa: `tests/Kit/SiteDeDocumentacaoTest.php:1131` meng-assert
jumlah berkas test yang tertulis di `README.md` + `README.en.md`. Berkas guard baru menaikkan total
177 → 178. Kedua README sudah diperbarui (imun, `naArvoreDoKit()` membatasi test itu ke pohon kit
saja), dan file itu hijau lagi (66 passed).

**Tripwire yang wajib diingat setiap fase berikutnya:** menambah/menghapus satu berkas test pun
meminta dua baris README disinkronkan. Ini bagus — artinya tidak ada cara diam-diam untuk
mengubah wajah fundasi — tapi harus jadi langkah manual di runbook Fase 6.

Lingkungan: `.env` belum ada di checkout ini, jadi `cp .env.example .env && php artisan
key:generate` adalah langkah 0 sebelum test apa pun berjalan (tanpa `APP_KEY`, bahkan
`tests/Feature/ExemploTest.php` bawaan merah dengan `MissingAppKeyException`).

### Biaya Fase 2 yang terukur sekarang (bukan estimasi)

Rename tabel menyentuh **18 berkas**, dan 7 di antaranya adalah test milik hulu — ini konsekuensi
yang harus diterima sadar-sadar, karena Fase 1 justru terbukti nol-sentuh-test:

| String tabel | Berkas yang perlu diubah | Catatan |
| --- | --- | --- |
| `'convites'` | 5 `tests/Tenancy`, 2 `tests/Kit`, model `Convite`, 1 config | `config/kit.php:'convites'` adalah kunci fitur, **bukan** tabel — jangan tersentuh |
| `'projetos'` | 1 `tests/Tenancy`, model `Projeto` | |
| `agentes_ia` | `app/Models/AgenteIa.php`, `app/Ai/**` (teks exception) | |
| `vinculos_sociais` | `app/Models/VinculoSocial.php` | |
| (tidak diubah) | 6 migration historis | create PT jalan lebih dulu, rename `2099_*` menyusul |

### KOREKSI RENCANA — hasil pengukuran Fase 2 (2026-02… 2026-09-22)

Kriteria verifikasi lama berbunyi "`git diff --stat database/migrations` harus menunjukkan 4 file
baru, **nol file lama berubah**". **Kriteria itu salah, dan PHPStan yang membuktikannya.** Yang
terjadi nyata:

`vendor/bin/phpstan analyse` jatuh dengan 2 error
(`app/Traits/BelongsToTenant.php:75-76`, *in the context of class* `App\Models\Projeto`:
"Access to an undefined property `Projeto::$tenant_id`"). Sebabnya: **Larastan memetakan kolom model
dengan mencari migration `create_*` yang membuat tabel yang sama** — dan table itu dicari dari
`$table`, bukan dari nama class. Begitu `Projeto` menunjuk `projects` sementara pohon migration masih
menciptakan `projetos`, Larastan tidak menemukan skema apa pun untuk `projects`, jatuh ke blok
`@property` (yang pada `Projeto` memang tidak mencantumkan `tenant_id` — itu kolom yang diisi trait),
dan oráculo statis jadi buta.

Ini versi tabel dari bahaya yang sama yang sudah saya catat untuk kolom di Fase 7 — dan berarti
**Fase 2 tidak bisa selesai tanpa menulis ulang migration historis**. Bukan tambal `@property`:
menambal satu kolom menyisakan oracle buta untuk semua kolom lain di tabel itu.

Yang dilakukan (7 berkas, `git diff` terverifikasi, **nama file tidak berubah** — `migrations`
melacak berdasarkan nama file, jadi mengubah isi file yang sudah jalan adalah no-op di DB lama):

```
0001_01_01_000010_create_agentes_ia_table.php          'agentes_ia'       → 'ai_agents'
0001_01_01_000022_create_projetos_table.php            'projetos'         → 'projects'
2026_08_13_000002_create_convites_table.php            'convites'         → 'invitations'
2026_08_14_000001_add_recusado_em_to_convites_table.php
2026_08_14_000002_add_lembretes_to_convites_table.php
2026_08_18_000001_add_soft_deletes_to_projetos_table.php
2026_08_26_172455_create_vinculos_sociais_table.php    'vinculos_sociais' → 'social_links'
```

Ini bukan penyimpangan dari rencana — ini justru **persis** pekerjaan yang rencana tugaskan ke
"rewriter mode `schema`" pasca-merge, hanya saja ternyata dibutuhkan di hari pertama, bukan nanti.
Konsekuensi bagus: `migrate:fresh` sekarang menghasilkan skema English **langsung**, dan migration
`2099_*` yang ber-guard `hasTable` menjadi no-op di jalur itu sambil tetap melayani DB produksi lama.
Dua jalur, satu hasil — yang memang sudah ditulis di docblock migrationnya.

Kriteria verifikasi yang benar sekarang: `git diff --stat -- database/migrations/` = **1 baru, 7
diubah**; `grep -rn "'convites'|'projetos'|'agentes_ia'|'vinculos_sociais'" database/migrations/`
**kosong** kecuali `2099_*`; dan `phpstan analyse` hijau. Bila upstream mengirim migration baru
bernama PT, ia masuk daftar yang ditulis ulang oleh rewriter.

**Jebakan yang sudah menggigit sekali:** PHPStan menyimpan *result cache* di
`%TEMP%/phpstan`. Setelah menulis ulang migration, analisis ulang **masih** melaporkan error lama —
dan itu membuat kesimpulan "rewrite tidak membantu" terasa benar. `vendor/bin/phpstan
clear-result-cache` sebelum menyimpulkan apa pun tentang error yang muncul setelah perubahan skema.
Setelah cache dibersihkan: **nol error**. (Larastan memang memetakan kolom model dari pohon
migration, jadi rewrite historis adalah perbaikan yang tepat, bukan tambal `@property`.)

**Hasil terukur Fase 2 (2026-09-22):** `tests/Feature` 16 passed (termasuk guard skema baru);
`--testsuite=Tenancy` **417 passed, 1.571 asersi**; `--testsuite=Kit` **2.356 passed** (uma queda
de README-stats explicada abaixo); `pint --dirty` limpo; `filacheck` 17/17 regras;
`phpstan analyse` **zero erros**.

Segunda queda do tripwire de README, e ela ensina algo: `SiteDeDocumentacaoTest.php:960` não conta
só arquivos de teste — ele sincroniza **migration, policies, comandos `kit:*`, pacotes, specs e
`.ai/rules`** com os dois READMEs. Qualquer arquivo novo nessas seis categorias exige as duas
linhas atualizadas. Registrar no runbook da Fase 6 como passo obrigatório, não como "conserto".

Ambiente: não havia `.env` nem sqlite de dev neste checkout (até `tests/Feature/ExemploTest.php`
nascia vermelho com `MissingAppKeyException`), então `migrate:fresh` foi provado via
`RefreshDatabase` em sqlite `:memory:`. **Verificações que continuam pendentes para qualquer pessoa
que rodar isto de verdade: o engine MySQL do docker e `php artisan kit:tenancy` nos dois modos.**

### PENUTUP BARIS 13 — engine MySQL nyata, dua jalur terukur (2026-09-22, server hidup)

Yang di atas ditulis saat `vendor/` belum ada dan MySQL belum menyala. Sekarang engine-nya nyata:
**MySQL Community Server 26.7.0** (`SELECT VERSION(), @@version_comment`), DB `starterkit` di
127.0.0.1:3306, `APP_URL=http://localhost:8000` (`/` 200, `/admin/login` 200).

Karena mengubah `starterkit` berarti merusak DB yang sedang disajikan server, jalur **DB produksi
lama** dibangun di scratch `starterkit_legado` — dan dibangun dengan **migration HEAD yang asli**
(`git archive HEAD database/migrations`), bukan karangan: 7 file ber-nama tabel PT cocok persis
dengan daftar rewrite di atas. Hasilnya kondisi produksi sejati: tabel PT ada, constraint bernama PT,
baris `2099` belum tercatat. Empat tabel diisi baris nyata (3/2/2/2) dengan parent FK sungguhan.

| Yang diukur di MySQL | Hasil |
| --- | --- |
| `migrate` jalur produksi lama (`2099_*` fire betulan) | `DONE` 166 ms; keempat nama English ada, keempat nama PT **nol** |
| Data ikut rename? | `invitations` 3 · `projects` 2 · `ai_agents` 2 · `social_links` 2 — identik sebelum/sesudah |
| FK setelah rename | tetap mengikat ke `users`/`roles`/`tenants`; `INSERT` dengan `role_id=999999` ditolak **SQLSTATE[23000]** (bukan cuma ada di metadata) |
| Klaim docblock "nama constraint/ indeks tetap PT" | **TERBUKTI**: `invitations` masih menggendong `convites_role_id_foreign`, `convites_email_index`, `convites_token_unique`, `convites_uuid_unique` (6 indeks `convites*`) |
| Eloquent lewat `$table` eksplisit | `Convite→invitations (3)` · `Projeto→projects (2)` · `AgenteIa→ai_agents (2)` |
| `down()` nyata | `migrate:rollback` mengembalikan keempat nama PT **dengan data utuh** (3/2/2/2), `invitations` hilang |
| Jalur `migrate:fresh --seed` (tenancy ON) | **nol** tabel PT, keempat English ada, `model_has_roles.team_id` = true, seed `DONE`, `2099_*` tercatat sebagai no-op |

**Dua koreksi terhadap rencana Fase 2, keduanya hasil mengukur bukan mengingat:**

1. **"3 widget SQL mentah" ternyata nol.** `OrganizacoesStats` hanya menyentuh `tenant_user` (sengaja
   dipertahankan) + `authentication_log` lewat `config('authentication-log.table_name')`;
   `UsuariosUnicosPorOrganizacao` dan `AcessosPorPainel` pun tidak menyebut salah satu dari keempat
   tabel. Tidak ada satu file widget pun yang perlu diubah — estimasi 14–18 file turun.
2. **`migrate --pretend` BUTA untuk migration ini, dan ini jebakan runbook.** Di DB yang keempat
   tabel PT-nya benar-benar ada, `--pretend` mencetak empat `select exists(...)` dan **nol**
   `rename table`. Sebabnya di vendor: `Schema\Builder::hasTable()` memakai `scalar($sql)`, dan
   `Connection::run()` mengembalikan `[]`/`null` saat `pretending()` (`Connection.php:427-430`) →
   guard kita jadi `false` → rename di-skip. Operator yang melakukan dry-run produksi menyimpulkan
   "tidak ada yang dikerjakan" padahal ada empat rename menunggu. **Fase 6 harus menulis ini**:
   dry-run yang jujur untuk `2099_*` adalah membaca `information_schema`, bukan `--pretend`.

Sisa baris 14 yang **tidak** bisa saya klaim, dan sekarang resmi diterima begitu (keputusan user
2026-09-22): **mode OFF tidak diuji di MySQL**. Alasannya teknis — rename keempat tabel tidak punya
satu pun dependency ke tabel permission (`invitations` hanya *menggunakan* `roles`/`tenants`/`users`,
tidak ada yang FK ke keempatnya), jadi `teams=false` tidak mengubah jalur yang Fase 2 tulis.
Menjalankan `kit:tenancy` betulan juga tidak menambah sinyal: tenancy hari ini **sudah** ON, sehingga
perintahnya short-circuit di `config('kit.tenancy.enabled')` dan hanya menjawab "já está ligado" —
setelah itu ia `migrate:fresh --seed` di `starterkit`, DB yang sedang disajikan `localhost:8000`.
Kalau suatu saat OFF perlu dibuktikan, jalurnya adalah `config/permission.php` (`'teams' => true`,
literal, bukan `env()`) + `config/filament-shield.php` (`tenant_model`) dan app hidup ikut berubah
bersama keduanya.

**Tripwire baru yang sudah menggigit:** `.raja/` tidak ada di `.gitignore`, jadi `git add -A` menelan
direktori scratch saya — commit `560dd0c` memasukkan 60 salinan migration lama di
`.raja/temp/legado/**`. Sudah dibereskan: `git rm -r --cached .raja` + `/.raja/` di `.gitignore`
(60 deletion masih staged, menunggu commit user). **Aturan untuk fase berikutnya**: scratch wajib
ter-ignore sebelum `git add -A` apa pun, dan masuk langkah manual di runbook Fase 6.

## HASIL BARIS 16 + premis baris 15 yang mati (dijalankan 2026-09-22)

### Baris 15: paket yang rencana kunci tidak ada

`composer show -a momin-alzaraa/localization` → *Package not found*; `repo.packagist.org/p2/…json`
→ **404**; pencarian GitHub untuk vendor itu → **`total_count: 0`**. Jadi `wikis/pacotes-ranking.md`
item #49 ("⚔️ com `momin-alzaraa/localization`, que gera os arquivos varrendo resources") menunjuk
nama yang tidak bisa dipasang. Keputusan user: **mesin = scanner pihak ketiga untuk PENEMUAN + rewrite
milik kita**. Yang nyata dan sepadan di Packagist: `php-translation/extractor` (4,71 M unduhan) dan
`gettext/php-scanner` (696 ribu) — keduanya menemukan/mengindeks string, **tidak** mengganti literal
di tempat dan tidak menulis overlay `lang/pt_BR.json`. Rewrite itu tetap `i18n:extract` kita, hanya
saja sekarang tanpa ilusi "instal alat, selesai".

### Inventário terukur (`.raja/temp/inventarioliteral.php`, token-based, bisa dijalankan ulang)

| | Rencana | Terukur |
|---|---|---|
| kemunculan | 890 | **1453** |
| distinct | 812 | **1278** |
| file | 129 | **163** |
| interpolatif | tidak dihitung | **181** |
| berakhiran `.`/`!`/`?` | 259 | **370** |

Per bucket: jalur render (allowlist Fase 4) **1098** — `app/Filament` 576, `resources/views` 345,
Ai 42, Http 40, Notifications 31, Policies 28, Providers 24, Livewire 12; di luar = `Console/Commands`
201, `app/Support` 124, `app/Models` 24. Selisih dengan rencana bukan noise: angka lama tidak
memasukkan pecahan interpolasi (`" de "`, `"Sem "`) dan node teks Blade. **Konsekuensi desain**:
unit ekstraksi harus kalimat utuh, bukan token — kalau tidak, `lang/pt_BR.json` berisi serpihan yang
tidak bisa disunting manusia; dan 181 interpolatif naik ke depan baris 18, bukan belakangan.

### Baris 16 selesai: 28 situs, 14 file

Estimasi rencana "±24" melesah; yang terukur **32 deklarasi properti statis de rótulo**, dan 4 di
antaranya sengaja TIDAK dikonversi:

- `Infra/Pages/Pulse.php` ×2 (`$title`/`$navigationLabel` = `'Pulse'`) — bukan PT, konversi = churn
  tanpa sinyal.
- `Admin/Widgets/ConvitesPorSituacao.php` + `Infra/Widgets/IaExecucoesPorStatus.php` (`$subheading`)
  — **terukur mati**: `grep subheading` tidak menghasilkan apa pun di `vendor/filament/widgets` maupun
  di `leandrocfe/filament-apex-charts`. Tidak ada yang pernah membaca properti itu di v5, jadi
  mengubahnya jadi getter **menemukan subtítulo que não existe na tela**. Dilaporkan, tidak disentuh
  (`.ai/rules`: dead code sebut, jangan hapus sendiri).

Yang dikonversi (`protected static ?string $X = '…'` → getter, literal PT byte-per-byte sama):
6 `Pages` + 7 `Resources` + 1 `RelationManager`.

**Jebakan yang membuat baris ini tidak bisa dikerjakan dari ingatan:** di Filament 5.8.2
`getTitle()` punya **dua** bentuk yang berbeda. `Filament\Pages\BasePage::getTitle()` adalah method
**instance** (`:85`, `string|Htmlable`), sementara `Filament\Resources\RelationManagers\RelationManager::getTitle(Model, string)`
adalah **static** dan dipanggil dengan argumen oleh vendor (`:161`, `:313`; juga
`Resources/Pages/Page.php:168` memakai `static::getTitle()`). Salah satu dari dua itu = `Error`
runtime di layar yang tidak disentuh test mana pun. Nama dan static-ness diambil dari source vendor,
bukan dari asumsi; `Resources\Pages\Page` (pemanggil statis) tidak ada di antara 14 file ini, jadi
tidak ada call site yang berubah arah.

Dua hal lain yang harus ikut ditulis dan tidak terlihat dari diff:

1. `EscolhaDePainel` dan `BoasVindas` hanya punya `$title`. `Page::getNavigationLabel()` (`:229`)
   membaca `static::$navigationLabel ?? static::$title` — setelah properti hilang, fallback itu jadi
   `class_basename()`. Keduanya mendapat `getNavigationLabel()` eksplisit supaya label navigasi tetap
   sama, bukan hanya `getTitle()`.
2. Guard baru `tests/Feature/LabelPainelLewatGetterTest.php` (43 kasus / 105 asersi) memakai
   **refleksi**, bukan regex, karena `.ai/rules/testes.md` mencatat tiga insiden di mana varredura
   menghitung komentar sebagai kode — dan file-file ini bersdokumentasi PT lebat.

### Gerbang yang dijalankan (baris 16)

`php artisan test --testsuite=Feature` → **59 passed (130 asersi)** · `vendor/bin/phpstan
clear-result-cache && analyse` → **0 erros** (cache dibersihkan dulu; tanpa itu error lama menipu)
· `vendor/bin/filacheck` → **17/17** · `pint --dirty` → bersih · smoke di server hidup:
`curl localhost:8000/` masih merender `Bem-vindo ao Starter Kit Easy` · **bukti guard bisa membunuh**:
`git checkout HEAD -- Admin/Resources/Convites/ConviteResource.php` (versi properti statis) membuat
guard **2 merah**, lalu dipulihkan.

## HASIL BARIS 29–31 — English default aktif + gancho global (dijalankan 2026-09-22)

### Keputusan user yang mengubah rencana (2026-09-22)

Locale = **`en` (default) + `id` + `pt_BR`** — bahasa Indonesia masuk sebagai locale, diterjemahkan
dari sumber Brazil. `pt_BR` **tetap** karena ~90 file test hulu meng-assert PT byte-per-byte: ia
oracle, bukan kenang-kenangan. Fase 7 **masuk scope penuh** (kolom, class, namespace, slug) dan
seed/factory **ikut English**. Urutan yang dipilih: aktifkan English dulu, ekstraksi per area.

### Dua koreksi terhadap temuan Fase 0 (terukur, bukan diingat)

1. **`translateLabel()` TIDAK ada di kelas dasar `Filament\Schemas\Components\Component`.**
   Fase 0 mencatat "satu registrasi di kelas dasar berlaku untuk semua subclass" — itu benar untuk
   *mekanisme* `ComponentManager::configure()` (menelusuri `array_reverse(class_parents())`), tapi
   **tidak** untuk *kepemilikan method*: di 5.8.2 `Section` memakai trait `HasLabel` sendiri, sementara
   `Column`/`BaseFilter`/`Summarizer`/`Action` memakainya di kelas dasar. Registrasi buta di `Component`
   = `Call to undefined method` pada komponen yang tidak punya label. Yang dipasang sekarang:
   **7 registrasi (Component, Column, ColumnGroup, BaseFilter, Summarizer, Action, Constraint) dengan
   satu `method_exists()`** — jadi komponen tanpa label hanya lewat, dan subclass baru dari upstream
   sudah tercakup tanpa siapa pun ingat mendaftar.
2. **Gancho hanya mencapai `$label`.** Medido: `Section::make('Teks')` menulis ke `$heading`, dan
   `getHeading()` tidak pernah lewat translator. Jadi "~300 call sites tidak perlu `__()`" berlaku
   untuk `->label()`, **bukan** untuk heading, `Text::make()` (isi), badge dan tooltip. Ada case
   eksplisit menegaskannya di `tests/Feature/LabelFilamentLewatOverlayTest.php` supaya nobody
   menyimpulkan sebaliknya dari file itu sendiri.

### Yang mendarat

- `LocalizacaoProvider::terjemahkanLabelDasar()` — file milik proyek, imun struktural. Dipanggil di
  `boot()` **tanpa** gate `runningUnitTests()`: suíte juga butuh overlay (ia mengukur PT).
- `config/localization.php` — `idiomas: ['en','id','pt_BR']`, `formatos.id`.
- `lang/id.json` BARU (20 kunci) + `lang/pt_BR.json` 33 → **53 kunci**.
- **Area 1 = 29 getter label** (title/navLabel/modelLabel/pluralModelLabel di 14 file
  `app/Filament/**`) jadi `__('English')`. Ditulis oleh skrip presisi yang locate lewat regex per
  *blok method* — `return 'Convites';` di dalam `match` arms adalah nilai banco, dan membungkus yang
  itu dengan `__()` akan merusak perbandingan.
- `.env` dan `.env.example`: `APP_LOCALE=en`, `APP_FAKER_LOCALE=en_US`.

### Sengaja TIDAK dikerjakan (dan alasannya)

- **17 fallback inline `config('kit.tenancy.label', 'Organização')` di 9 file.** Skrip pertama saya
  ikut mengubahnya; saya revert semuanya. Alasannya: `config/kit.php:353` sudah menyelesaikan
  `env(...) ?: '…'` lebih dulu, jadi default inline itu **kode mati** — mengubahnya = 17 baris konflik
  di file whitelisted demi nol perubahan perilaku. Pemilik label tenancy yang sebenarnya adalah
  `KIT_TENANCY_LABEL` di `.env` (imun), dan itu yang saya ganti ke `Organization`/`Organizations`.
  Konsumen baca nilai config mentah tanpa translator: membuatnya locale-aware itu pekerjaan Fase 5.
- `getSubheading()` (2) dan `getDescription()` (1) — baris 16 sudah membuktikan `$subheading` statis
  mati di v5; sebelum menyentuh versi method-nya harus diukur siapa yang memanggil.
- `Administração` (nav group), `Papéis` (Shield), dan seluruh isi widget/dashboard: area 2.

### Gerbang

`Feature` **65 passed / 139 asersi** (naik dari 59; 6 kasus guard baru) · `pint --dirty` bersih ·
`phpstan analyse` (setelah `clear-result-cache`) **0 erros** · smoke HTTP: `/admin/login` →
`?locale=en` "**Sign in**", `?locale=id` "**Masuk**", `?locale=pt_BR` "**Esqueceu…**" — tiga locale
resolve, default English · panel `/admin` sudah login: `Application settings`, `AI agents`,
`Invitations`, `Users`, `Dashboard`. **Belum**: `composer test:kit` penuh di atas area 1 (baris 33).

## Fase 1 — Plumbing imun + kerangka alarm (2–3 hari)

`config/localization.php` (baru): `'suite' => 'pt_BR'`, `'formatos' => [...]`.
`app/Support/IdiomasDoPainel.php` (baru): pemilik daftar locale produksi.
`app/Providers/LocalizacaoProvider.php` (baru), didaftarkan di akhir `bootstrap/providers.php`
(imun; `phpstan.neon:100-105` menganalisis `bootstrap/`, jadi cukup satu baris tambahan):

```php
public function boot(): void
{
    if ($this->app->runningUnitTests()) {
        app()->setLocale(config('localization.suite'));   // suite mengukur konfigurasi KIT (pt_BR)
        return;                                           // produksi tidak pernah lewat sini
    }

    config()->set('kit.idiomas', IdiomasDoPainel::lista());
    // + Date::setLocale(), Number::useLocale() — lihat Fase 5
}
```

Dua keputusan desain di baliknya, dan keduanya punya alasan keras:

- **Kenapa pin lewat provider, bukan `.env.testing`**: Laravel memuat **satu** berkas env.
  `LoadEnvironmentVariables::checkForSpecificEnvironmentFile()` menemukan `.env.testing`
  (karena `phpunit.xml:122` menyetel `APP_ENV=testing`) lalu `safeLoad()`-nya **menggantikan**
  `.env` — ia harus ikut membawa `APP_KEY` (dipakai payload settings terenkripsi,
  `tests/Kit/SegredosDoSettingsTest.php`), `DB_*`, dst., dan `.gitignore` hanya mengabaikan
  `.env`/`.env.backup`/`.env.production` → `.env.testing` akan ter-commit berisi kunci.
  `phpunit.xml` boleh tetap ditambahkan 2 baris `<env name="APP_LOCALE" value="pt_BR" force="true"/>`
  sebagai sabuk-pengaman (dokblock-nya sendiri, `:48-62`, sudah menyatakan doktrin yang sama) — tapi
  itu berkas whitelisted, jadi bukan pemegang utama.
- **Kenapa `kit.idiomas` lewat `config()->set()` dan bukan suntingan `config/kit.php:571`**: ada test
  hulu yang memblokir — `tests/Kit/PacotesTierSTest.php:153-157` meng-assert
  `config('kit.idiomas') === ['pt_BR']` **dan** `LanguageSwitch::isVisibleInsidePanels() === false`.
  Berkas itu whitelisted → menyuntingnya = churn yang di-revert update berikutnya. Ordering aman:
  `ConfiguraFilamentGlobal.php:172-209` membaca `config('kit.idiomas')` **di dalam closure** yang
  baru dieksekusi saat render (docblock `:174-183` menjelaskan ini sebagai bug fix) → `set()` di
  `boot()` provider selalu lebih awal. Gate `runningUnitTests()` didokumentasikan di docblock, dan
  guard di bawah menguji daftar produksi langsung dari `IdiomasDoPainel::lista()` supaya gate tidak
  bisa menyembunyikan revert. Layak dibuka isu upstream: test itulah hambatan sebenarnya.

Tiga guard test, semuanya di `tests/Feature/**` (tidak dipindai `KitUpdateTest::DIRETORIOS_DE_CODIGO`,
tidak whitelisted → tidak perlu mengubah daftar apa pun; `RefreshDatabase` sudah tersambung lewat
`tests/Pest.php:37-39`, dan CI sudah menjalankan suite `Unit,Feature,Kit,Tenancy`):

- `IdentificadoresDoBancoSaoInglesTest` — `Schema::hasTable('invitations')` true **dan**
  `Schema::hasTable('convites')` false **dan** `(new Convite)->getTable()` berbahasa English.
  Separuh pertama menangkap migration rename yang hilang, separuh kedua menangkap `$table` di
  `app/Models/Convite.php` (file whitelisted) yang di-revert.
- `LocalizacaoPadraoDoProjetoTest` — `IdiomasDoPainel::lista() === ['en','pt_BR']`; provider terdaftar
  di `bootstrap/providers.php` (scan statis, teknik yang sudah dipakai `tests/Pest.php:369-374`);
  tiap kunci milik kita di `lang/pt_BR.json` benar-benar resolve
  (`expect(__($k))->not->toBe($k)`); `config('localization.suite') === 'pt_BR'` dan
  `app()->getlocale() === 'pt_BR'` di dalam suite.
- `NenhumTextoPortuguesNoCodigoTest` — dipasang di Fase 4 (butuh ekstraksi selesai lebih dulu).

Verifikasi: `composer test --testsuite=Unit,Feature,Kit,Tenancy` — **suite penuh hijau tanpa satu
string pun berubah**. Ini gerbangnya: kalau pin locale tidak bekerja, semuanya berhenti di sini.

## Fase 2 — Schema: rename 4 tabel saja (2–3 hari)

Target: `agentes_ia`→`ai_agents`, `projetos`→`projects`, `convites`→`invitations`,
`vinculos_sociais`→`social_links`. Sisanya sudah English; `tenant_user` **tetap** (pivot yang
di-infer Filament dan spatie — imbal hasil kosmetik, risiko nyata).

Estimasi nyata: **14–18 file.**

- 4 migration baru, **prefix timestamp jauh ke depan**: `2099_01_01_000001_rename_convites_to_invitations_table.php`
  ×4, masing-masing dengan `down()` nyata. Menaruhnya di `2026_09_*` justru berbahaya: migration
  hulu berikutnya `2026_10_*_add_x_to_convites_table.php` akan sort **setelah** milik kita dan
  menabrak tabel yang sudah berganti nama. Ordering-by-prefix sudah jadi gaya rumah
  (`0001_01_01_0000{10,20,21,22}_*`). Template docblock: mengikuti
  `database/migrations/2026_08_16_000001_rename_admin_organizacao_role.php`, yang menyatakan
  migration adalah satu-satunya jalur yang didistribusikan `kit:update` ke DB orang lain.
- 4 model: `AgenteIa.php:39` dan `VinculoSocial.php:26` mengubah `$table` yang ada;
  `Convite.php` dan `Projeto.php` **mendapat** `$table` — hari ini keduanya bergantung pada konvensi
  nama class, dan **keabsenan** `$table` itulah yang membuat rename diam-diam batal saat hulu
  mengembalikan file-nya. Ini baris terpenting di fase ini.
- 3 widget SQL mentah: `OrganizacoesStats.php:54,106-114`, `UsuariosUnicosPorOrganizacao.php:81-90`,
  `AcessosPorPainel.php:77` (`DB::table('tenant_user')` tetap).
- ±6 file test yang menyebut nama tabel langsung (`tests/Kit/TravaDeExclusaoTest.php`;
  `tests/Pest.php:938` sudah tidak langsung via `getTable()` — bagus).

`migrate:fresh` dan DB produksi dua-duanya benar by construction: tabel lama tracked by filename
sehingga tidak dijalankan ulang, rename jalan sekali dan `Schema::rename()` memindahkan data; di
fresh install hasilnya tetap tabel English. FK hanya keluar (`convites`: `role_id`, `tenant_id`,
`convidado_por_id`) — tidak ada yang menjadikan keempat tabel ini parent, jadi ini arah yang aman.

Verifikasi: `php artisan migrate:fresh --seed && composer test:kit`, **lalu engine kedua**
(MySQL lewat docker, `.env.docker`/`docker-compose.yml`) — SQLite butuh `legacy_alter_table=OFF`
untuk perilaku FK yang sama, jadi jangan percaya sqlite saja. Lalu `php artisan migrate --pretend`
di DB tiruan produksi, dan `git diff --stat database/migrations` harus menunjukkan 4 file baru,
nol file lama berubah.

## Fase 3 — Ekstraksi ±812 literal (12–18 hari, ekor panjang)

Pakai plugin `momin-alzaraa/localization` untuk mekanisasi (didukung 5.x; ia "menghapus label
hardcoded dan membuat translation key"). Jalankan di branch percobaan pada **satu** direktori dulu,
lalu paksa hasilnya memenuhi invarian Desain A: literal English di kode, PT masuk `lang/pt_BR.json`,
byte PT identik dengan yang dihapus. Untuk yang tidak bisa ditangani plugin, `i18n:extract` milik
kita (token-based, allowlist bentuk pemanggilan) adalah cadangan — lihat daftar di bawah.

Yang harus tetap dikerjakan tangan, berapa pun nilai plugin-nya:

1. **Properti statis** — `protected static ?string $title = 'Configurações da aplicação'`
   (`ConfiguracoesDoKit.php:84,86`) tidak bisa berisi `__()` (PHP menuntut ekspresi konstan).
   **±24 situs** (8 `$title`, 8 `$modelLabel`, 8 `$pluralModelLabel`, plus `$navigationLabel`) →
   ubah jadi getter (`public static function getTitle(): string`). Kerjakan di commit pertama,
   sendiri, dengan nama getter v5 diverifikasi ke vendor.
   Catatan: `$recordTitleAttribute = 'nome'` (9 situs) adalah **nama kolom**, bukan label — tidak
   boleh disentuh di sini (itu Fase 7).
2. **String interpolatif** — `"...{$app}..."` (`app/Notifications/PrimeiroAcessoSocial.php:43-48`,
   `ConviteDeAcesso.php:61-88`) → kunci dengan placeholder `:app`.
3. **Blade** — `resources/views/{errors,filament,livewire,auth}/**` ±120 string; hanya node teks di
   luar `@php` dan atribut `:label=`/`label=`/`title=`, **tidak pernah** `class`/`href`/`id`/`x-*`.
4. **Tabrakan kunci**: `lang/pt_BR.json` sudah memuat `"Package"`, `"Details"`, `"Close"`,
   `"Backups"`, `"Installed"`, `"Repository"`. Ekstraktor harus **fail closed dan melapor** kalau
   kunci yang sama punya nilai berbeda — salah menerjemahkan modal milik paket lain adalah kelas bug
   yang dilarang `.ai/rules/config.md:31`.

Urutan commit (satu area per commit, masing-masing dengan potongan `lang/pt_BR.json`-nya):
`app/Support`+`Policies` → `Notifications`+`Providers` → `Ai` → `Http` → `Filament/Admin` →
`Filament/App` → `Filament/Infra` → `Filament/{Pages,Concerns}` → `resources/views` → label
`config/kit.php`.

Bahasa Inggris ditulis manusia/agent, bukan MT mentah — istilah Filament punya konvensi
(`Roles`, `Permissions`, `Organizations`, `Invitations`, `Trash`, `Observability`).

**Bukti byte-identity PT tidak perlu alat kedua.** 90 file test hulu sudah me-assert PT yang
dirender (`tests/Kit/BoasVindasTest.php:36` `assertSee('Bem-vindo ao Starter Kit Easy')`,
`:163-166` `'7 dias'`/`'14 dias'`). Kalau satu byte PT bergeser, `composer test:kit` merah dengan
string pelakunya. Aturan praktis Fase 3: **merah di suite Kit saat ekstraksi = drift PT, bukan
regresi kode.**

Verifikasi per commit: `composer test` (sudah termasuk `pint --test`, `phpstan analyse` level 7,
`filacheck`), plus `vendor/bin/filacheck --fix` tiap kali menyentuh `app/Filament`.

## Fase 4 — Nyalakan alarm utama (1 hari)

`NenhumTextoPortuguesNoCodigoTest` jadi hard-fail. Desainnya:

- **Tokenizer (`token_get_all()`), bukan regex.** Ini luka berulang di repo ini:
  `tests/Pest.php:1219-1224` sudah menyediakan helper `semComentarios()` dengan docblock *"Só para
  asserção de AUSÊNCIA"*, dan `.ai/rules/testes.md:48-71` mencatat tiga insiden di mana asersi
  kelengkapan gagal karena komentar berkasnya sendiri. Komentar/PHPDoc tersingkir otomatis lewat
  tipe token → ini jawaban terbaik untuk "bagaimana tidak ikut menandai komentar PT yang memang
  harus tetap PT".
- **Allowlist path = seluruh desain guard.** Hanya yang dirender: `app/Filament/**`,
  `app/Policies/**`, `app/Notifications/**`, `app/Ai/**`, `app/Http/**`, `app/Livewire/**`,
  `app/Providers/**`, `app/Data/**`, `resources/views/**` **kecuali** `resources/views/vendor/**`
  (salin pengecualian yang sudah dibuat `KitUpdateTest.php:139-143`).
- **Yang sengaja tidak dipindai, dengan alasannya di pesan gagal:**
  `app/Console/Commands/**` (±153 kemunculan — ini keluaran CLI, dan `KitInfoTest`/`PacotesTierSTest`
  meng-assert baris PT via `tests/Pest.php:1085-1094`; menerjemahkannya merusak suite kit tanpa
  manfaat UI); `app/Support/CustomizadorDaInstalacao.php` (46) dan `HostLocal.php` (23) — generator
  yang menulis prosa PT ke `.env`/config; `database/{seeders,factories}/**` — data demo, itu input
  bukan copy (kecuali Anda ingin demo English: lihat Keputusan terbuka); `config/kit.php`,
  `.ai/rules`, `wikis/`, `docs/`, `README*` — dokumentasi.
- **Keluaran harus bisa ditindaklanjuti**: `arquivo:linha` + literal + bentuk `__('…')` yang tinggal
  ditempel. Guard yang mencetak 40 nama file adalah guard yang akhirnya dibungkam orang.
- **Kontrol positif** (wajib, `.ai/rules/testes.md:92`: *"O oráculo está vivo?"*): satu kasus yang
  menjalankan scanner atas fixture inline `<?php $x = 'Organização';` dan assert ia **tertangkap**.
  Tanpa ini, bug di traversal membuat guard hijau selamanya — repo ini sudah pernah kena
  "46 kasus hijau di atas apa pun" (`tests/Pest.php:1107`).

## Fase 5 — Formatting & buka sakelar bahasa (4–7 hari)

- **Masker tanggal, dari config, dan kalau bisa satu knob global.** 28 titik
  (`d/m/Y`/`H:i`/`translatedFormat`/`->format('`) di `app/Filament/**/Tables`, `Schemas`, `Widgets`,
  `Concerns/{CabecalhoDeUsuario,FichaDeUsuario}.php`, `Infra/Resources/AiRuns`. Wiki proyek sudah
  menyarankan rumahnya: `wikis/specs/feature/v1-enriquecimento-kit/08-comparativo-mini-pff.md:71-80`
  menyebutnya *"alto valor, baixíssimo custo … cabe em ConfiguraFilamentGlobal"*. Kalau Filament v5
  punya knob level panel (hasil Fase 0 #2), ia jadi **2 baris** di `configuraTable()`
  (`ConfiguraFilamentGlobal.php:222-281`, yang sudah mengatur default tabel sekalian). Fallback:
  ganti 28 masker jadi `->dateTime(config('localization.formatos.data_hora'))`. Jangan buat kelas
  wrapper — `KitServiceProvider.php:207-212` menolak menerbitkan `config/livewire.php` dengan alasan
  yang sama persis.
- **`Resource::titleCaseModelLabel(false)` (`ConfiguraFilamentGlobal.php:80`): BIARKAN.** Godaan
  setelah flip adalah mengaktifkannya; tiga alasan menolak: `ucwords()` tetap salah untuk
  "Configurações da aplicação"; perilaku per-locale **tidak bisa** hidup di static yang di-set sekali
  di `boot()` (jebakan yang sama didokumentasikan `:174-183` untuk `kit.idiomas`); dan under Desain A
  setiap label English sudah lewat `__('Organizations')` yang kapitalisasinya benar — `false` justru
  mencegah Filament merusak apa yang kita tulis.
- **Trio locale di satu tempat** (production path provider + listener `LocaleUpdated`):
  `Date::setLocale()`, `Number::useLocale()` (membiayai 8 exporter + `IaTokensPorDriver.php:80` +
  `UltimoBackup.php:111`). Fallback `lang/vendor/{filament-lockscreen,asmit-resized-column,filament-search-spotlight}/en/`
  untuk tiga paket yang hanya punya PT.
- **Label tenancy** — `config/kit.php:353-354` fallback `'Organização'/'Organizações'` → English, dan
  konsumen membaca lewat translator. Ini aman **khusus karena** `phpunit.xml:67-68` mem-pin
  `KIT_TENANCY_LABEL=Organização` dengan `force="true"` sementara `config/kit.php:353` adalah
  `env(...) ?: '…'`: berkas test whitelisted-lah yang membuat berkas config whitelisted aman diubah.
  Konsekuensi untuk runbook: kalau `phpunit.xml` di-revert, label default jadi English dan test PT
  merah — kegagalan yang keras dan benar, dan guard §3c menjelaskan penyebabnya.
  `.env` Anda sendiri: `APP_LOCALE=en`, `APP_FAKER_LOCALE=en_US`.
- **`.env.example:17-20` ikut diubah (3 baris).** `.env` tidak pernah disentuh `kit:update`, jadi
  deployment Anda aman — tapi CI (`ci.yml:28,66,99`) dan `post-root-package-install` melakukan
  `cp .env.example .env`, sehingga **setiap clone baru fork ini diam-diam kembali Portuguese**. Itu
  kegagalan yang Anda takutkan, lewat pintu lain. Berkas whitelisted → churn 3 baris, murah.
  Cek dulu: `grep APP_LOCALE tests/` kosong, jadi tidak ada test yang meng-assert baris tersebut —
  tapi konfirmasi dengan `composer test:kit` segera setelah edit.
- **Zona waktu (bug nyata, bersebelahan)**: `config/app.php:94` men-hardcode `'UTC'` sehingga
  `APP_TIMEZONE=America/Sao_Paulo` di `.env.example` mati. `config/app.php` imun → perbaikan gratis
  (`env('APP_TIMEZONE','UTC')`). **Jangan digabung commit apa pun**: mengaktifkannya menggeser
  rendering `now()` bagi suite yang hari ini diam-diam berjalan UTC sementara `.env`-nya mengklaim
  lain — batas `d/m/Y` dan perbandingan `expira_em` ikut bergerak. Mendarat sendirian di akhir.
- Buka `IdiomasDoPainel::lista() = ['en','pt_BR']` → switcher yang sudah terpasang
  (`ConfiguraFilamentGlobal.php:170-210`, `->visible(count>1)`, termasuk di 3 rute login) muncul
  sendiri.

Verifikasi: `php artisan test --testsuite=Browser` (yang benar-benar merender tanggal), `grep -rn
"d/m/Y" app/` kosong di luar `config/localization.php`, dan smoke manual `/admin?locale=en` ↔
`?locale=pt_BR` di tiga panel + tiga layar login.

## Fase 6 — Kontrak update & runbook (1–2 hari)

**Vonnis: `git merge` dari remote hulu, bukan `kit:update`.** Alasannya teknis, bukan selera:
`aplicar()` di `KitUpdate.php:887-893` melakukan `git checkout <tag> -- <path>` — penggantian penuh
tanpa three-way merge — dan `confirmarLote()` (`:874-878`) sendiri memperingatkan *"Os modificados
SUBSTITUEM o conteúdo atual"*. Setelah Fase 3, `app/Filament/**` akan punya ±800 suntingan lokal di
129 file whitelisted; jawaban benar untuk setiap tawaran "modificado" adalah "pular" — artinya
Anda tidak menerima perbaikan bug-nya. Jawaban salahnya adalah satu tekan Enter di
*"Aplicar TUDO"* (`:854`) dan seluruh kerja i18n hilang dengan `git status` hijau. Merge melakukan
kebalikannya: hunk yang hanya Anda ubah auto-resolve ke pihak Anda.

```bash
# 0. titik balik
git switch -c kit-update/<tag> ; git tag pre-kit-update-$(date +%F) ; git status --porcelain  # kosong

# 1. vincular (namespace tag yang sama seperti yang dipakai KitUpdate)
git remote add kit https://github.com/gsferro/filament-starter-kit-easy.git
git remote set-url --push kit no_push
git fetch --no-tags kit 'refs/tags/*:refs/tags/kit-*'

# 2. merge, konflik per hunk
git merge kit-<tag>
#    berkas imun (lang/pt_BR.json, config/localization.php, provider, tests/Feature) -> MILIK KITA selalu menang
#    kawasan kit -> menang hulu, lalu:
php artisan i18n:extract --report      # lihat apa yang kembali
php artisan i18n:extract               # terapkan ulang
php artisan config:clear && php artisan filament:assets
composer install ; php artisan migrate

# 3. gerbang merah-nya-di-sini
composer test:kit ; composer test ; php artisan test --testsuite=Feature   # 3 guard
```

`composer.json` dapat three-way diff proper dari merge (via `kit:update` ia sengaja report-only,
`:306-308`). `config/kit.php:'version'` akan konflik — ambil theirs, lalu re-apply fallback label
§4c bila ikut berubah. Kalau terpaksa memakai `kit:update`: yang aman hanyalah `--dry-run` dan
`--only-new` (hanya berkas yang belum ada di tree Anda, jadi tidak pernah menimpa) plus
`--keep-remote` supaya sisanya bisa Anda `git diff kit-vA kit-vB -- <arquivo>` per file;
`--all` tidak pernah aman di fork ini. Setelah satu run sukses, `marcarVersao()` (`:1051-1081`)
sudah menulis ulang versi → run kedua butuh `--from=` eksplisit (`:1020-1029`) atau ia menjawab
"Nada a atualizar" sementara berkas dari daftar path versi baru masih hilang.

Deliverable lain fase ini: `.ai/rules/idioma.md` (berkas baru = imun; entri `.ai/rules` sudah
prefix-covered sehingga `KitUpdateTest` tidak protes) + satu baris di `.ai/rules/index.md`, dan
runbook dua bahasa (`docs/en` + `docs/pt`) — paritas dipaksa test (`KitUpdateTest.php:448`,
`CitacoesDeCodigoTest.php:64`), jadi menulis satu bahasa saja akan merah. Opsional: satu step CI
`php artisan i18n:extract --report --fail` (`.github/**` imun, `export-ignore`).

## Fase 7 (opsional, berbiaya terukur) — kolom, class, namespace, slug

Anda memilih kedalaman ini. Sebelum Anda menguncinya, ini angka hasil pengukuran — dan dua di
antaranya adalah hambatan keras yang tidak ada di percakapan kita sebelumnya:

- **Kolom**: `nome` menyentuh **110 file kode + 96 file test**; `painel` **133 + 125**;
  `ativo`/`provedor` 40 masing-masing; sisanya 5–23. ≈250 file kode untuk `nome`+`painel` saja.
- **Jaring pengaman statis rusak by construction**: PHPStan level 7 membaca blok
  `@property string $nome` (`app/Models/Convite.php:41-52`, `AgenteIa.php:21-35`), **bukan**
  database. Rename yang meleset dari `@property` = PHPStan hijau, autocomplete IDE menunjuk kolom
  yang tidak ada, `SELECT "nome"` pertama meledak di runtime. Fase 2 tidak punya lubang ini
  (`Schema::hasTable` itu nyata).
- **Rename class model membentrok guard whitelist hulu**: `CAMINHOS_DO_KIT` memasukkan `app/Models/*`
  **satu per satu** (`:118-131`) justru untuk menghindari tabrakan nama. `app/Models/Invitation.php`
  karena itu tidak dicakup entri apa pun → `tests/Kit/KitUpdateTest.php:116-159` gagal dengan
  "Arquivos do kit fora de CAMINHOS_DO_KIT", dan satu-satunya jalan memperbaiki adalah menyunting
  `NAO_E_DO_KIT` — konstanta di **file test upstream**, yang di-revert update berikutnya.
  Rename class juga menyeret slug/route turunan Filament, nama permission Shield
  (`ViewAny:Invitation` vs baris ter-seed `ViewAny:Convite`), string `auditable_type` di `audits`,
  `model_type` di `media`/`model_has_roles`/`model_has_permissions`/`recycle_bin_items`/`ai_runs`,
  dan payload `database/settings` yang mende/serialisasi nama properti PHP → rename properti settings
  adalah migrasi data, bukan skema.
- **`provedor`/`painel` bukan cuma kolom — itu kontrak wire**: `/auth/{provedor}/callback`
  (`ProvedorSocial` enum backed; yang masuk URL adalah `->value`), `/login/painel/{painel}`,
  `config/kit.php:614` `tenancy.slug`. Mengubah `->value` membatalkan allowlist callback yang sudah
  Anda daftarkan di Google/GitHub/LinkedIn/X dan semua baris audit. Rename *case* enum gratis;
  rename *value* tidak.

Rekomendasi saya, dengan angka ini di tangan: **kirim Fase 0–6, tunda Fase 7.** Kalau tetap ingin,
urutannya: (a) kolom jarang-tersentuh sebagai gladi (`convidado_por_id`, `token_lembrete`,
`recusado_em` ≤6 file) → (b) `expira_em`/`aceito_em`/`versao`/`descricao`/`temperatura` →
(c) `nome`/`painel`/`ativo`/`provedor` paling akhir dan **tidak pernah** satu rilis dengan
`kit:update`; kolom diizinkan lewat jika Anda juga menaikkan PHPStan ke level yang membaca skema
(atau menambah guard `Schema::getColumnListing()` vs `@property`). Untuk rename class + slug,
syaratnya sama: perluasan rewriter (`i18n:rename` via Rector `RenameClassRector` + pemindahan
berkas) plus migrasi data FQCN/permission, dan `git checkout --theirs` atas file hulu lalu
re-apply.

## Verification (end-to-end)

1. `composer install` (vendor hari ini belum ada).
2. `php artisan test --testsuite=Feature` → 3 guard hijau; kontrol positif membuktikan scanner hidup.
3. `composer test:kit` → hijau **tanpa satu baris pun di `tests/Kit/**` diubah** — ini bukti pt_BR
   utuh byte-per-byte dan satu-satunya oracle byte-identity yang Anda butuhkan.
4. `composer test` → `pint --test` + `phpstan analyse` + `filacheck` + suite lengkap.
5. `php artisan migrate:fresh --seed` di sqlite **dan** MySQL docker (dua engine, karena FK/rename);
   `php artisan route:list --except-vendor` untuk cek slug.
6. `php artisan test --testsuite=Browser` → tanggal, nomor, dan layar English vs pt_BR.
7. Uji produksi tiruan: backup (`spatie/laravel-backup` sudah ada) → `migrate --pretend` → `migrate`
   → cek baris `model_type`/`permissions.name` kalau Fase 7 jalan → login → panel terbuka.
8. **Tes sebenarnya dari "tidak akan tertimpa"**: di branch coba, `git merge` tag hulu berikutnya
   (atau `git checkout kit-v0.38.0 -- app/Filament/Admin/Resources/Convites` sebagai simulasi revert
   paling jahat) → `php artisan i18n:extract` → `php artisan test --testsuite=Feature` hijau lagi.
   Lakukan ini **setelah Fase 2 dan lagi setelah Fase 3**, jangan di akhir proyek.

## Risiko utama

1. **Volume Fase 3** (12–18 hari, 129 file whitelisted) — risiko utamanya bukan kode tapi copy:
   drift PT membuat test hulu merah dan godaan berikutnya adalah "memperbaiki" test milik hulu.
   Aturan besi: merah di `composer test:kit` = koreksi overlay, bukan koreksi test.
2. **Fase 0 belum terjawab**. Kalau `__()` tidak resolve kunci bertitik, atau Filament v5 tidak punya
   knob format tanggal global, rencana Fase 3/5 punya cabang fallback yang sudah ditentukan di atas —
   tapi keduanya mengubah estimasi. Jangan mulai Fase 3 sebelum Fase 0 ditutup.
3. **Fase 7 merusak jaring pengaman statis dan menyentuh kontrak wire** (OAuth callback allowlist,
   permission rows). Ini keputusan biaya, bukan kemampuan.
4. **Rewriter/plugin jadi aset permanen fork ini.** Kalau beban itu tidak sepadan, jalan mundur yang
   jujur adalah memangkas Fase 7 — Fase 0–6 tetap bernilai dan jauh lebih murah dipertahankan.

## Yang perlu Anda putuskan sebelum eksekusi

- **Apakah Fase 7 tetap masuk scope** setelah angka di atas (rekomendasi saya: tunda; kunci
  Fase 0–6 dulu). Kalau "ya", mulai dari gladi kolom kecil, bukan dari `nome`.
- **Data demo English**: Anda memilih "ganti ke English", tapi itu berarti menyunting
  `database/{seeders,factories}/**` (wilayah hulu) dan guard §4 harus mengecualikannya karena data
  demo bukan copy UI. Konfirmasi: English untuk seed demo (churn/upkeep lebih tinggi) atau tetap PT?
- **Nama English final** entitas domain — saya pakai default `invitations`, `projects`, `ai_agents`,
  `social_links`, `panel`, `organization`/`organizations` kalau Anda tidak mengoreksi.
- `README.md`/wiki hulu yang berbahasa PT **dibiarkan** (dokumen upstream, `wikis/*.md` whitelisted).

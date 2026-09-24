
Starter kit **Laravel 13 + Filament 5** yang siap pakai. Satu perintah membuat project, menginstal semuanya, menjalankan migrasi, mengisi seed database, dan menyerahkan tiga panel yang langsung berfungsi: **bisnis**, **administrasi** dan **infrastruktur**.

```powershell
composer create-project ridwans2/filament-starterkit my-project `
  --repository='{"type":"vcs","url":"https://github.com/ridwans2/starterkit.git"}'
cd my-project
composer dev
```

Flag `--repository` wajib: kit ini didistribusikan langsung dari GitHub, bukan dari Packagist.

Tidak ada langkah manual: `create-project` sudah membuat `.env`, membangkitkan `APP_KEY`, membuat database, menjalankan migrasi, melakukan seed role/permission/user, memublikasikan aset Filament, dan membangun frontend. Di akhir proses dicetak URL dan login awal.

Sebelum menyentuh database, installer **mengajukan lima pertanyaan** — sama seperti `laravel new`:

| | Pertanyaan | Default |
|---|---|---|
| 1 | Nama project | nama folder |
| 2 | Database | SQLite · **PostgreSQL** (direkomendasikan: satu-satunya dengan `pgvector`, dibutuhkan fitur AI lokal) · MySQL |
| 3 | E-mail dan password administrator | `admin@example.com` / `password` |
| 4 | Warna primer panel | default Filament |
| 5 | Multi-tenancy | off |

**Menekan Enter di semua pertanyaan memasang persis seperti sebelumnya** — tidak ada pertanyaan yang wajib, dan pertanyaan pertama adalah "kustomisasi sekarang?", yang sekaligus melewati semuanya. Tanpa terminal (CI, Docker, `--no-interaction`) tidak ada yang ditanyakan. Di akhir, installer mencetak ringkasan apa yang berubah, apa yang masih harus diedit manual, dan menawarkan untuk menjalankan test suite kit.

> **Di Windows pertanyaan tidak muncul, dan itu bukan bug kit.** Diukur di kedua shell,
> PowerShell dan Git Bash: Composer tidak pernah mengaktifkan TTY di Windows — `ProcessExecutor::runProcess()`
> mematikan mode TTY ketika `Platform::isWindows()`, karena `symfony/process` akan melempar
> `TTY mode is not supported on Windows platform`. `artisan` menerima pipe, dan installer
> melewati dirinya sendiri lewat terminal guard bawaannya, dan mengatakannya di layar.
>
> **Yang harus dilakukan**, dan urutannya penting:
>
> ```powershell
> php artisan kit:install --force    # lima pertanyaan — MEMBUAT ULANG database
> ```
>
> Jalankan **tepat setelah instalasi**, saat database hanya berisi data seed: di sana
> `--force` tidak berbahaya. Nanti, setelah ada data, perintah ini destruktif — ia menghapus
> file SQLite sebelum bertanya.
>
> Jika database sudah berisi data dan Anda hanya ingin mengganti nama dan warna:
>
> ```powershell
> php artisan kit:install --custom   # nama dan warna, tidak menyentuh yang lain
> ```
>
> Tiga pertanyaan lainnya tidak punya versi non-destruktif, dan perintah itu menjelaskan alasannya:
> database dan multi-tenancy membutuhkan pembuatan ulang (tabel permission hanya mendapat kolom
> tenant sebelum `migrate`), dan kredensial administrator **tidak disinkronkan oleh seeder** —
> seeder menjamin bahwa seorang administrator ada, bukan bahwa ia mencerminkan `.env`, karena ia
> berjalan di setiap `db:seed` dan menimpa di sana akan mengembalikan password yang diubah manual.
>
> Untuk mengubah e-mail atau password administrator, jalurnya disengaja:
>
> ```powershell
> php artisan kit:admin
> php artisan kit:admin --email=new@example.com --senha=secret --force   # tanpa prompt — hindari: password masuk ke riwayat shell
> ```
>
> Ia meminta konfirmasi, tidak pernah menampilkan password di layar, menolak e-mail yang sudah
> dipakai akun lain, dan **berhenti** jika ada lebih dari satu `master_global` — alih-alih memilih
> satu berdasarkan urutan. Layar profil di panel juga bisa.
>
> Di Linux, macOS dan WSL pertanyaan muncul saat `create-project` dan semua ini tidak diperlukan.

> Multi-tenancy adalah item yang paling merugikan jika ditunda: diaktifkan saat instalasi tidak memakan biaya apa pun; diaktifkan belakangan, `kit:tenancy` **membuat ulang database** (tabel permission hanya mendapat kolom tenant jika flag aktif sebelum migrasi).

![Memasang starter-kit-easy dengan satu perintah](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/install.gif)

Lebih suka clone? Installer yang sama berjalan mandiri:

```powershell
git clone https://github.com/gsferro/filament-starter-kit-easy.git my-project
cd my-project
Remove-Item -Recurse -Force .git   # buang riwayat git milik kit
git init
composer setup
```

## Akses demo

Seeder membuat user master yang langsung bisa masuk ke ketiga panel:

| | |
|---|---|
| **User** | `admin@example.com` |
| **Password** | `password` |
| **Role** | `master_global` (menembus permission apa pun lewat `Gate::before`) |

Masuk di `/app`, `/admin` atau `/infra` — satu session berlaku untuk ketiganya, dan menu user berpindah panel.

> ⚠️ **Ganti password sebelum lingkungan ini dibuka ke luar.** Agar lahir dengan kredensial berbeda, set `KIT_ADMIN_EMAIL`, `KIT_ADMIN_PASSWORD` dan `KIT_ADMIN_NAME` di `.env` **sebelum** menjalankan instalasi (nilainya ada di `config/kit.php`). Pada project yang sudah terpasang, ubah dari panel di `/admin/users` atau lewat **Profil saya**.

Untuk melihat batas akses bekerja, buat user dengan role `admin` atau `infra` saja: ia masuk ke panel yang sesuai dan mendapat 403 di panel lainnya.

## Tiga panel

| Panel | URL | Untuk apa | Siapa yang masuk |
|---|---|---|---|
| **App** | `/app` | Operasional bisnis. **Sengaja kosong** — di sinilah project Anda lahir | `master_global`, `panel_user`, `admin_app` (dengan tenancy) |
| **Admin** | `/admin` | User, role dan permission (Shield), katalog AI agent, penyusunan onboarding | `master_global`, `admin` |
| **Infra** | `/infra` | Health check, backup, queue, log, exception, jejak email, tempat sampah, auditing, cache, perintah, Pulse, biaya AI | `master_global`, `infra` |

**Siapa yang masuk ditentukan oleh role, bukan oleh daftar di kode.** Setiap role mendeklarasikan panel mana yang cocok untuknya, di kolom `roles.painel` — field **Painel** di layar `/admin` → Roles. `App\Models\User::canAccessPanel()` membandingkan kolom itu dengan panel yang dibuka. Membuat role dan memilih panelnya **adalah** tindakan memberi akses.

Null **bukan** wildcard: role tanpa panel hanya membawa permission dan tidak membuka panel apa pun. Role `master_global` masuk ke ketiganya lewat jalur lain — ia menembus gate apa pun lewat `Gate::before` (`App\Providers\KitServiceProvider`), tanpa permission di database, dan `canAccessPanel()` langsung meloloskannya sebelum melihat kolom itu.

Di panel **tanpa** tenancy (`/admin`, `/infra`) role harus diberikan di konteks global: menjadi `admin` di satu organisasi bukan syarat untuk mengelola instalasi. Di `/app` role berlaku di organisasi mana pun — organisasi mana yang dibuka diputuskan kemudian oleh `canAccessTenant()`.

**Badge di menu user menampilkan role untuk organisasi yang SEDANG dibuka.** Seseorang yang berada di lebih dari satu organisasi bisa memegang role berbeda di masing-masing — `panel_user` di satu, `admin_app` di lainnya — dan badge mengikuti perpindahan organisasi. Tanpa role di organisasi yang dibuka, tidak ada badge: masuk panel tidak bergantung pada organisasi (paragraf di atas), tetapi tampilannya bergantung. Tidak ada yang berubah di panel tanpa tenancy, karena di sana tidak ada organisasi aktif.

> Dengan [multi-tenancy](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/multi-tenancy.html) aktif, **App** menjadi `/app/{tenant}` dan hanya menampilkan data tenant terpilih. Admin dan Infra tetap global.

Memisahkan admin dari infra adalah seluruh tujuan kit ini: siapa pun yang mengelola user tidak butuh (dan tidak seharusnya) melihat log, queue, dan perintah operasional, begitu pula sebaliknya.

### Tampilan masing-masing panel

| Login | Administrasi |
|---|---|
| [![Layar login](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/login.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/login.png) | [![Panel admin](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/panel-admin.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/panel-admin.png) |
| Auth Designer dua kolom — artwork menampilkan nama aplikasi | User, role, AI agent dan indikator administrasi |

| Infrastruktur | Bisnis |
|---|---|
| [![Panel infra](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/panel-infra.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/panel-infra.png) | [![Panel app](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/panel-app.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/panel-app.png) |
| Health, queue, jejak audit, perintah dan biaya AI — dikelompokkan di Observability, AI, Trails dan System | Sengaja kosong: di sinilah project Anda lahir |

Layar lainnya: [kesehatan aplikasi](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/infra-health.png) · [user](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/admin-users.png) · [permission (Shield)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/admin-roles.png) · [katalog AI agent](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/admin-agentes-ia.png) · [pusat perintah](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/infra-comandos.png) · [pencarian ⌘K](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/spotlight.png) · [akses ditolak](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/erro-403.png)

## Angka-angka kami

Bukan pamer: ini daftar lengkap semua yang sudah ada, dan semua yang tidak perlu Anda tulis.

| | `/app` | `/admin` | `/infra` | **Total** |
|---|---:|---:|---:|---:|
| **Layar yang bisa dinavigasi** | 14 | 31 | 28 | **73** |
| Resources | 4 | 8 | 8 | **20** |
| Halaman mandiri | 5 | 5 | 13 | **23** |
| Widgets | 1 | 9 | 19 | **29** |
| Rute `GET` | 23 | 38 | 34 | **95** |

`/app` paling kecil memang disengaja — ia lahir **kosong**, karena di situlah bisnis Anda masuk.
Dua sisanya sudah datang lengkap.

> **Kriteria setiap baris, supaya angkanya bisa diaudit.** *Layar yang bisa dinavigasi* adalah rute
> `GET` panel **dengan nama rute**, tidak termasuk rute autentikasi, endpoint JSON dan redirect.
> *Halaman mandiri* adalah `$panel->getPages()`, tanpa pengecualian. *Rute `GET`* menghitung semuanya
> di bawah path panel, termasuk autentikasi dan redirect. *Widgets* adalah `$panel->getWidgets()` —
> widget **panel**; 6 widget resource di `Tenants/Widgets/` tidak ikut, karena itu `ls`
> mengembalikan angka yang lebih besar. Diukur dengan `kit.tenancy.enabled = false`; dengan tenancy aktif,
> rute `/app` mendapat prefix organisasi.
>
> Sampai v0.36.1 baris *Layar yang bisa dinavigasi* tidak punya kriteria yang dideklarasikan dan
> **tidak bisa difalsifikasi** — angkanya `12 / 28 / 27` sejak 2026-08-18 dan lolos dari satu putaran
> fact-check tanpa dikoreksi, karena tidak ada cara untuk memeriksanya. Kelima baris kini dikunci oleh
> `tests/Kit/SiteDeDocumentacaoTest.php`.

| Fondasi | |
|---|---:|
| Paket produksi | **58** |
| Paket development | **19** |
| Migrasi | **61** |
| Policies | **16** |
| Perintah `kit:*` | **8** |

| Kualitas | |
|---|---:|
| Test case (`Kit` + `Tenancy`, diukur 2026-09-08) | **2.226**, dengan **7.428 assertion** |
| Layar yang disapu di browser asli | **55** |
| File test | **151** di `Kit` + `Tenancy` (**184** total) |
| PHPStan | **level 7**, nol error |
| FilaCheck | **17** aturan, semua lulus |

| Dokumentasi | |
|---|---:|
| Dokumen referensi (`wikis/`) | **10** |
| Feature yang terspesifikasi (`wikis/specs/`) | **66** |
| Aturan project untuk AI agent (`.ai/rules/`, tanpa indeks) | **19** |

> Detailnya pindah ke situs: **[Referensi](https://gsferro.github.io/filament-starter-kit-easy/en/referencia/)** dan **[Memulai](https://gsferro.github.io/filament-starter-kit-easy/en/comecar/)**.

## Yang sudah tersedia

**Pintu depan**
- **Halaman welcome di rute `/`**, menggantikan welcome default Laravel: satu kartu per panel plus
  hasil kustomisasi `kit:install` ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/autenticacao/rota-publica.html))

**Administrasi dan keamanan**
- Shield (role dan permission dengan UI) di atas spatie/laravel-permission
- Breezy: profil user, avatar, 2FA dan passkeys
- **Halaman record user layar penuh** (`/admin/users/{id}` dan `/app/users/{id}`): akun, status, asal dan tanggal, dengan tombol edit di atas. **Hanya di `/admin`** daftar organisasi dan role orang tersebut muncul — di `/app` penghilangan itu justru fiturnya: memberi tahu mereka berarti memberi tahu pengelola satu organisasi bahwa akun itu juga milik organisasi lain ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/operacao/roteiro-de-features.html))
- **Avatar digambar di dalam aplikasi**: tanpa foto, provider default Filament membuat browser setiap orang meminta `ui-avatars.com` di setiap layar — membawa inisial di query string dan `Referer` panel besertaannya. `App\Support\AvatarDeIniciais` mengembalikan SVG inline di ketiga panel: tampilannya sama, tanpa request eksternal
- Auth Designer: layar login dua kolom — artwork **menampilkan nama aplikasi**, dibaca dari `APP_NAME` setiap kali dimuat; untuk memakai gambar sendiri, unggah di `/admin/configuracoes-da-aplicacao`
- **Pendaftaran terbuka opsional** (mati secara default): registrasi tanpa undangan di `/app`, dengan satu role, persetujuan manual dan verifikasi email — masing-masing di balik key sendiri ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/autenticacao/registro-aberto.html))
- Impersonate, log autentikasi, audit perubahan (owen-it)
- Panel Switch: berpindah panel dari menu user
- **DTO dengan `spatie/laravel-data`**: setiap nilai terstruktur yang melintasi batas kelas adalah objek bertipe, dengan penjaga otomatis — kredensial tidak pernah masuk ke DTO, dan konsumsi/respons API mewajibkannya ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/dto-com-laravel-data.html))
- **Proteksi anti-robot opsional** (mati secara default): reCAPTCHA v2/v3, Turnstile atau hCaptcha di layar login, reset password dan register, lewat `ddr/filament-captcha` ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/autenticacao/protecao-anti-robo.html))
- **Halaman login tunggal** (mati secara default): `KIT_LOGIN_UNIFICADO=true` mengirim `/admin/login`, `/infra/login` dan `/app/login` ke `/login`; siapa pun yang bisa mengakses satu panel langsung masuk, yang bisa mengakses lebih dari satu memilih dari layar kartu setelah login. **Perhatian**: SSO eksternal (SAML / OIDC korporat) belum dipraset dan tidak melewati aturan itu — lihat [halaman doknya](https://gsferro.github.io/filament-starter-kit-easy/en/autenticacao/login-unificado.html)
- **Login sosial per panel**: setiap provider bisa diaktifkan terpisah untuk `/app`, `/admin` dan `/infra`; tombol, rute dan tujuan menghormati panel asal

**Observabilitas dan pemeliharaan (panel infra)**
- Spatie Health dengan pemeriksaan database, cache, queue, scheduler, disk (kecuali di Windows), mode debug, environment, app teroptimasi, dan AI lokal
- Backup Monitor (spatie/laravel-backup), Jobs Monitor, Logs Explorer (tanpa tombol hapus — jejak adalah bukti)
- **Exception dikelompokkan** berdasarkan jenis dan frekuensi — yang tidak dijawab Health, Pulse, dan file log
- **Jejak email terkirim**: memisahkan "tidak pernah terkirim" dari "terkirim dan masuk spam"
- **Tempat sampah**: memulihkan yang dihapus dengan `SoftDeletes` ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/trilhas-de-infraestrutura.html))
- Command Center: perintah Artisan yang sudah disetujui untuk UI, dengan riwayat
- Laravel Pulse tertanam sebagai halaman panel
- Dependency Graph: peta model, relasi, resource dan panel
- Release Notifier: memberi tahu saat ada versi baru paket Composer

**AI (opsional, lokal secara default)**
- `laravel/ai` dengan katalog agent di database: system prompt, provider, model, tools dan guardrail adalah **data**, diedit di `/admin` tanpa deploy
- Guardrail berantai: budget, prompt injection, classifier lokal, redaksi PII dan filter output sensitif
- Ledger eksekusi (`ai_runs`) dengan biaya dan token di panel infra
- Widget chat dengan streaming
- Inferensi 100% lokal lewat llama.cpp (`docker compose --profile ai up -d`) atau provider SaaS apa pun dengan mengganti `AI_PROVIDER`

**Produktivitas**
- **Pencarian ⌘K** menggantikan field native di topbar: menemukan record, layar, halaman dan aksi pembuatan — semuanya discoping oleh permission (detail di bawah)
- Badge hitung beranimasi di menu, pusat notifikasi dengan tab, indikator environment
- **Dashboard sudah terisi** di panel admin dan infra: 29 widget (stat card dengan counter beranimasi, funnel, target, rincian, timeline) di atas data yang sudah dimiliki panel — termasuk login hari ini dengan riwayat tujuh hari dan, dengan tenancy, wawasan organisasi dan akses
- Halaman error ber-brand (Sentinel) dalam bahasa Portugis (pt-BR) — yang 403 hanya menampilkan diagnosis permission di luar production
- UI 100% pt-BR, termasuk plugin yang hanya membawa bahasa Inggris (terjemahan di `lang/vendor/`)
- **Pemilih bahasa** di ketiga panel dan di layar login — digerakkan oleh data, bukan oleh flag (detail di bawah)
- **Lapisan media** (spatie/laravel-medialibrary) di dalam komponen Filament: unggah, koleksi dan konversi di form, table dan infolist ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/anexos-e-midia.html))
- **Header kaya** di atas layar view dan edit untuk user dan organisasi: avatar (atau inisial), nama, badge status dan metadata yang bisa disalin, menggantikan judul teks polos milik Filament
- **Peringatan perubahan belum disimpan** saat meninggalkan form di ketiga panel, dan **versi sistem Anda di footer** — keduanya adalah sakelar di `/admin/configuracoes-da-aplicacao`, tanpa deploy ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/configuracoes-do-kit.html))
- **Kerapatan layout tiga tingkat**, di layar pengaturan yang sama: *confortável* (default Filament), *compacto* dan *denso*. Ia mengencangkan stat card, tabel, sidebar dan tombol di ketiga panel sekaligus, dan **berlaku pada refresh berikutnya** — tanpa theme Vite, tanpa `npm run build`, tanpa deploy. Diukur pada kit itu sendiri: tinggi baris tabel turun **−17,1%** di compacto dan **−23,4%** di denso ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/configuracoes-do-kit.html#densidade-do-layout))
- **Tautan langsung ke panel setiap organisasi** (dengan multi-tenancy) di ketiga layar `/admin` — list, view dan edit. Di list ia berupa kolom dengan alamat yang terlihat, terbuka di tab baru: Anda tahu tujuannya sebelum klik ([detail](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/multi-tenancy.html))

### Kerapatan layout, di layar yang sama

| Confortável (default) | Compacto | Denso |
|---|---|---|
| [![Kerapatan nyaman](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/densidade-confortavel.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/densidade-confortavel.png) | [![Kerapatan kompak](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/densidade-compacto.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/densidade-compacto.png) | [![Kerapatan padat](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/thumbs/densidade-denso.png)](https://raw.githubusercontent.com/gsferro/filament-starter-kit-easy/main/art/densidade-denso.png) |

Perhatikan kolom aksi: di *confortável* terpotong menjadi *"Edi…"*; di *denso* muat utuh.
Mengencangkan bukan soal tinggi saja — ia berhenti menyembunyikan konten.

## Dokumentasi lengkap

Yang dulu berada di sini — lebih dari dua ribu baris — kini punya situs sendiri, dengan pencarian dan
navigasi: **[https://gsferro.github.io/filament-starter-kit-easy/en/](https://gsferro.github.io/filament-starter-kit-easy/en/)**

| Grup | Yang Anda temukan di sana |
|---|---|
| [Memulai](https://gsferro.github.io/filament-starter-kit-easy/en/comecar/) | instalasi lanjutan, database, perintah, kustomisasi, dan cara memperbarui project yang lahir dari kit |
| [Autentikasi](https://gsferro.github.io/filament-starter-kit-easy/en/autenticacao/) | undangan, pendaftaran terbuka, login sosial, proteksi anti-robot, status user |
| [Fitur](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/) | multi-tenancy, lampiran dan media, impor/ekspor CSV, jejak `/infra`, pengaturan kit, card hub, DTO dengan Laravel Data |
| [Operasi](https://gsferro.github.io/filament-starter-kit-easy/en/operacao/) | AI agent, roadmap feature lengkap, konvensi, yang harus dilakukan setelah membuat Resource |
| [Referensi](https://gsferro.github.io/filament-starter-kit-easy/en/referencia/) | kualitas kode, pencarian dan bahasa, 77 paket yang terpasang |

Versi bahasa Portugis ada di **[https://gsferro.github.io/filament-starter-kit-easy/pt/](https://gsferro.github.io/filament-starter-kit-easy/pt/)**.

### Perbaikan masa depan

**[Roadmap kit](wikis/roadmap.md)** mencatat apa yang sudah ditinjau dan **dengan sengaja ditunda**,
beserta alasannya dan, jika ada, pengukuran di balik keputusannya: preferensi tampilan per user,
theme compact berbayar Filament, dan pemicu yang akan pensiunkan implementasi densitas saat ini.

Ia ikut terbungkus ke project Anda dengan sengaja — ini masa depan **kit**, bukan project Anda, dan
ia ada supaya Anda tidak menghabiskan sore mengevaluasi ulang sesuatu yang angkanya sudah tercatat.

## Persyaratan

- PHP 8.4+ dan Composer 2
- Node 20+ (opsional — tanpanya instalasi tetap berjalan dan memberi tahu cara build belakangan)
- Docker (opsional — hanya untuk Postgres/MySQL, Redis, AI lokal dan email)

## Database

**Instalasi bertanya** — SQLite, PostgreSQL atau MySQL. Default-nya **SQLite**, supaya tidak bergantung pada apa pun.

**PostgreSQL adalah yang direkomendasikan**, karena alasan fungsional: ia satu-satunya yang membawa `pgvector`, yang dibutuhkan fitur AI lokal dengan pencarian semantik (embeddings). Dengan SQLite atau MySQL sisanya berjalan sama — hanya fitur itu yang tidak tersedia.

**Postgres dan MySQL sama-sama punya container** — MySQL di profile-nya sendiri, karena instalasi memilih satu database. Perintahnya ada di bagian [Docker](#docker).

Jika Anda memilih Postgres saat instalasi, `.env` sudah membawa blok yang dibaca `docker-compose.yml`. Jika container belum aktif saat itu, kit memberi peringatan, **melewati migrasi**, dan mencetak perintah penyelesaiannya:

```powershell
docker compose up -d              # pgsql (dengan pgvector) + redis
php artisan migrate --seed
```

Untuk berpindah setelah instalasi, nyalakan container dan salin variabelnya:

```powershell
docker compose up -d              # pgsql (dengan pgvector) + redis
# salin blok database dari .env.docker ke .env Anda
php artisan migrate --seed
```

## Docker

Semuanya opt-in per profile. Satu container per fitur:

```powershell
docker compose up -d                            # pgsql (dengan pgvector) + redis
docker compose up -d mysql redis                # MySQL sebagai pengganti Postgres
docker compose --profile ai up -d               # + llama.cpp (chat dan embeddings)
docker compose --profile mail up -d             # + mailpit (1025 / 8025)
docker compose --profile full up -d             # seluruh infrastruktur
docker compose --profile app up -d --build      # aplikasi dalam container
docker compose --profile realtime up -d reverb pulse
```

| Service | Port | Profile |
|---|---|---|
| PostgreSQL 17 + pgvector | 5432 | base |
| MySQL 8 | 3306 | `mysql` |
| Redis 7 (cache saja) | 6379 | base |
| llama.cpp (chat) | 8080 | `ai` |
| llama.cpp (embeddings) | 8081 | `ai` |
| Mailpit | 1025 / 8025 | `mail` |
| App (nginx + php-fpm) | 8000 | `app` |
| Reverb (WebSocket) | 8090 | `app`, `realtime` |

Reverb memakai 8090 alih-alih 8080 default supaya tidak bentrok dengan llama.cpp.

Tidak ada service yang mendeklarasikan `container_name` tetap: prefix datang dari `COMPOSE_PROJECT_NAME`, yang ditulis `kit:install` dengan nama project Anda. [Detail di situs](https://gsferro.github.io/filament-starter-kit-easy/en/comecar/instalacao-avancada.html).

### Hostname lokal, alih-alih IP dan port

Anda bisa membuka project di `http://my-project.test` alih-alih `http://127.0.0.1:8000`: harganya satu baris di file `hosts` mesin dan dua key di `.env`. Tidak ada file versioned yang berubah, penganutnya individual, dan siapa pun yang tidak melakukan apa pun tetap di `http://localhost:8000`. [Resep lengkap, termasuk jebakan elevasi di Windows](https://gsferro.github.io/filament-starter-kit-easy/en/comecar/dominio-local.html).

**`kit:install` menawarkan ini di akhir instalasi**: ia mengusulkan domain dari nama yang Anda pilih, menulis baris ke `hosts` (meminta elevasi di Windows) dan menyesuaikan `APP_URL`. Ia opt-in, tidak bergantung flag, dan menolak membuat semuanya persis seperti selalu. Domain di luar suffix yang dicadangkan untuk penggunaan lokal (`.test`, `.localhost`, `.example`, `.invalid`) butuh satu "ya" tambahan, karena mengarahkan domain asli ke `127.0.0.1` memblokir akses ke situs sebenarnya dari mesin ini.

### Memperbarui stack di mesin yang menjadi host-nya

`./deploy_docker_local.sh` berjalan **di host container** (bukan di mesin development Anda) dan melakukan seluruh urutannya: `git pull`, rebuild image, `--profile app up -d`, migrasi, `optimize:clear`, pemeriksaan health di `/up` dan probe TCP ke Reverb. Output ditambahkan ke `storage/logs/deploy_docker_local.log`.

```powershell
# Skrip bash — jalankan lewat Git Bash atau di host Linux, bukan di PowerShell
./deploy_docker_local.sh
./deploy_docker_local.sh --recreate   # saat .env berubah
```

Rebuild datang **setelah** pull karena image-nya mandiri (kode dipanggang ke dalamnya) — rebuild lebih dulu akan memanggang kode lama. Dan karena ia membuat ulang `reverb` dan `pulse`, keduanya di profile `app` yang sama, tidak ada perintah restart terpisah: proses long-running tidak akan melihat kode baru tanpa restart.

`--recreate` menambahkan `--force-recreate`, dibutuhkan saat `.env` berubah: Compose membaca `env_file` ketika container **dibuat**, jadi container yang sudah ada mempertahankan nilai lama. Jika `.env.example` berubah di pull tersebut, skrip memberi peringatan.

## Perintah

```powershell
composer dev          # server + queue + vite sekaligus
composer test         # pint + phpstan + filacheck + seluruh suite
composer test:kit     # hanya test kit (fondasinya), paralel
composer lint         # memformat kode
composer lint:check   # hanya memeriksa format, tidak mengubah apa pun (yang dijalankan CI)
composer filament:check   # hanya lint khusus Filament (FilaCheck)
composer refactor:preview # apa yang akan ditulis ulang Rector (dry-run) — DI LUAR composer test
composer refactor:apply   # menerapkan tulisan ulang Rector — DI LUAR composer test
composer upgrade:filament # menjalankan vendor/bin/filament-v5 (filament/upgrade sudah ada di require-dev)
php artisan kit:install --force   # memasang ulang dari nol (menghapus file SQLite) dan bertanya lagi
php artisan kit:install --custom   # mengulang hanya nama dan warna, tanpa menyentuh database
php artisan kit:install --no-custom   # instalasi tanpa bertanya apa pun
php artisan kit:install --no-npm      # melewati instalasi dan build aset frontend
php artisan kit:install --no-seed     # tidak melakukan seed database (role, user awal, AI agent)
php artisan kit:install --no-support  # melewati ajakan membintangi kit di GitHub
#   --create-project internal milik post-create-project-cmd: menghapus yang hanya berguna untuk repo kit sendiri
php artisan kit:admin             # mengubah e-mail dan password administrator (meminta konfirmasi)
php artisan kit:admin --email=x --senha=y --force   # tanpa prompt — hindari: password masuk ke riwayat shell
php artisan kit:info              # menampilkan bagaimana project dikustomisasi dan dari mana tiap nilai datang
php artisan kit:update            # membawa perbaikan dari versi baru kit
php artisan kit:tenancy           # mengaktifkan multi-tenancy (opt-in)
```

## Kustomisasi project Anda

**Installer sudah menanyakan yang lima pertama** — daftar di bawah untuk mengubahnya belakangan, atau bagi yang melewati pertanyaannya.

| # | Apa | Di mana | Ditanya saat instalasi? |
|---|---|---|---|
| 1 | **Nama** | `APP_NAME` di `.env` | ✅ |
| 2 | **Database** | blok `DB_*` di `.env` | ✅ |
| 3 | **Kredensial seeder** | `KIT_ADMIN_EMAIL` / `KIT_ADMIN_PASSWORD` di `.env` | ✅ |
| 4 | **Warna primer** | `KIT_COR_PRIMARIA` di `.env` (nama warna dari palet Filament), atau `KIT_COR_PRIMARIA_HEX` dengan nilai hex bebas — hex mengalahkan nama saat keduanya terisi | ✅ |
| 5 | **[Multi-tenancy](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/multi-tenancy.html)** | `php artisan kit:tenancy`, dan istilah yang ditampilkan di `config/kit.php` → `tenancy.label` | ✅ |
| 6 | **Artwork login** | tidak ada: ia **menampilkan nama aplikasi** (`APP_NAME`) dengan sendirinya. Untuk menggantinya dengan gambar sendiri, unggah di `/admin/configuracoes-da-aplicacao` | ✅ (lewat nama) |
| 7 | **Akses panel** | role tiap user (`/admin` → Roles, field *Painel*); aturan yang membacanya adalah `App\Models\User::canAccessPanel()` | — |
| 8 | **Matriks permission** | `database/seeders/PapeisSeeder.php` | — |
| 9 | **Pemeriksaan health** | `KitServiceProvider::configureHealthChecks()` | — |
| 10 | **Perintah di UI** | `config/command-center.php` | — |
| 11 | **Backup** | tujuan dan jadwal di `config/backup.php` | — |
| 12 | **AI agent** | `/admin` → AI Agents (atau `database/seeders/AssistenteSeeder.php`) | — |
| 13 | **[Bahasa panel](https://gsferro.github.io/filament-starter-kit-easy/en/referencia/busca-e-idioma.html)** | `config/kit.php` → `idiomas` (daftar locale; dengan hanya satu, pemilih bahasa tidak muncul) | — |
| 14 | **[Retensi jejak](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/trilhas-de-infraestrutura.html)** | `KIT_RETENCAO_EXCECOES_DIAS` / `KIT_RETENCAO_EMAILS_DIAS` di `.env` | — |
| 15 | **[Disk media](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/anexos-e-midia.html)** | `MEDIA_DISK` di `.env` (`local` secara default — privat, dilayani lewat signed URL) | `php artisan kit:midia-privada` memigrasi media yang sudah tertulis ke disk publik |
| 16 | **[Impor dan ekspor CSV](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/import-export-csv.html)** | Action di tiap `app/Filament/**/Pages/List*.php` (aktif atau dikomentari); permission di `config/filament-shield.php` → `policies.methods`; retensi riwayat di `KIT_RETENCAO_IMPORTACOES_DIAS` / `KIT_RETENCAO_EXPORTACOES_DIAS` di `.env` | reseed `ShieldPermissionsSeeder` + `PapeisSeeder` setelah menyentuh config |

Sebelas yang terakhir tidak ditanyakan karena mereka **kode atau data layar**, bukan nilai yang muat di prompt terminal. Installer mendaftarkannya di ringkasan akhir, masing-masing dengan file-nya.

> ⚠️ Item 5 adalah satu-satunya yang **bukan** "edit sebuah file" setelah terpasang: `kit:tenancy` menjalankan `migrate:fresh --seed` dan **menghapus data Anda**. Ia membutuhkan git tree yang bersih dan konfirmasi eksplisit. **Dijawab saat instalasi ia tidak menghapus apa pun** — database belum ada, dan itulah momen yang tepat untuk memutuskannya.

> Warna primer berlaku di ketiga panel. Dengan [multi-tenancy](https://gsferro.github.io/filament-starter-kit-easy/en/recursos/multi-tenancy.html) aktif, warna setiap organisasi **menang** atasnya di dalam `/app/{slug}` — `/admin` dan `/infra` mempertahankan warna project. Untuk palet lengkap, dan bukan hanya `primary`, jalurnya tetap `->colors([...])` di tiap `app/Providers/Filament/*PanelProvider.php`.

## Memperbarui project yang lahir dari kit

**Kit adalah titik awal, bukan dependency.** Setelah `create-project` project menjadi milik Anda: Anda mengganti nama panel, mengubah `canAccessPanel()`, menyunting seeder. Karena itu **tidak ada** `kit:update` yang menimpa file — ia akan menulis ulang persis apa yang Anda kustomisasi, dan starter kit yang merusak project penggunanya tidak ada harganya.

Yang berubah terbelah menjadi tiga lapisan, dan masing-masing punya jalurnya sendiri:

| Lapisan | Apa itu | Cara memperbarui |
|---|---|---|
| **Dependency** | Filament, plugin, Laravel | `composer update` — ini sebagian besar perbaikan dan ia datang dengan sendirinya |
| **Lem kit** | providers, traits, widgets, view error | diff manual terhadap tag baru (di bawah) |
| **Bisnis Anda** | semua yang Anda tulis | tidak pernah disentuh |

## Pemecahan masalah

- **403 di semua panel, tepat setelah masuk** — user tidak punya role sama sekali, atau role-nya tidak punya panel yang dideklarasikan (`roles.painel` kosong bukan wildcard: ia tidak membuka apa pun). Berikan role di `/admin` → Users, atau isi field *Painel* di `/admin` → Roles.
- **`/infra` atau `/admin` mengembalikan 403** — user Anda butuh role yang panelnya panel itu (`master_global`, `admin` atau `infra`), dan dengan multi-tenancy aktif role harus diberikan di konteks global. Layar 403 menampilkan permission mana yang kurang, tetapi **hanya di luar production**: di production ia tidak membocorkan role maupun permission.
- **Aset Filament hilang** — `php artisan filament:assets`.
- **Pulse tanpa data** — daemon-nya belum ada: `php artisan pulse:check` (atau service `pulse` di compose).
- **Bel tidak ter-update real time** — `BROADCAST_CONNECTION=reverb` membutuhkan proses Reverb aktif; tanpanya kit jatuh ke polling 30 detik.
- **Asisten AI tidak tersedia** — nyalakan `docker compose --profile ai up -d` (boot pertama mengunduh model ~4,5 GB) atau ganti `AI_PROVIDER` ke provider SaaS dengan API key.

## Lisensi

MIT.

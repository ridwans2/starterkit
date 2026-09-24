<?php

namespace App\Console\Commands;

use App\Settings\ConfiguracoesDoKit;
use App\Support\BancoSqlite;
use App\Support\CustomizadorDaInstalacao;
use App\Support\HostLocal;
use App\Support\VinculoDoSnyk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;

/**
 * Deixa o projeto pronto para uso em um comando.
 *
 * Roda automaticamente no `composer create-project` (post-create-project-cmd)
 * e pode ser reexecutado à mão depois de um `git clone` — todos os passos são
 * idempotentes. Nenhum passo aborta a instalação: o que falhar vira aviso com
 * a instrução de como refazer.
 *
 * Num projeto NASCENDO ele também pergunta: nome, banco, credenciais do admin,
 * cor e multi-organização, no mesmo lugar em que o `laravel new` faz as dele.
 *
 * As perguntas dependem de o Composer conseguir repassar o terminal ao script
 * (`ProcessExecutor::executeTty`), e ele nem sempre consegue — sistema, console e
 * forma de invocação mudam o resultado. Sem terminal, nada é perguntado, a
 * instalação é a de sempre, e o comando avisa como refazê-la com as perguntas.
 * `temTerminal()` explica por que a detecção não é `isInteractive()` sozinho.
 */
class KitInstall extends Command
{
    protected $signature = 'kit:install
        {--no-npm : Lewati instalasi dan build aset front-end}
        {--no-seed : Jangan isi database (role, user awal, agen AI)}
        {--force : Membuat ulang database SQLite dari nol (MENGHAPUS data yang ada) dan mengulang lima pertanyaan}
        {--custom : Hanya mengulang yang tidak menyentuh database — nama dan warna — lalu keluar. Tidak menghapus apa pun}
        {--no-custom : Lewati pertanyaan kustomisasi dan instal dengan default}
        {--no-support : Lewati ajakan memberi bintang pada kit di GitHub}
        {--create-project : Penggunaan internal post-create-project-cmd: menghapus yang hanya berguna untuk repositori kit}';

    protected $description = 'Instal starter-kit: database, migrasi, seeder, permission, dan aset';

    /** Endereço do kit, para o convite da estrela. */
    private const REPOSITORIO = 'https://github.com/ridwans2/starterkit';

    /** @var list<string> */
    protected array $avisos = [];

    /**
     * Linhas do resumo da customização. Vazio = o usuário pulou.
     *
     * @var list<array{0: string, 1: string}>
     */
    private array $resumo = [];

    /** Falso quando o banco escolhido não respondeu: migrar sem conexão só empilha erro. */
    private bool $bancoAcessivel = true;

    public function handle(): int
    {
        $this->components->info('Menginstal starter-kit-easy...');

        $this->prepararEnv();

        /*
         * O boot aplicou o banco VELHO à config, e é dele que o `--force` vai se livrar. Sem
         * devolver a config ao `.env` ANTES das perguntas (o customizador realinha em memória o
         * que respondem), a migration de settings semearia o banco novo com o que o banco velho
         * dizia. Ver `ConfiguracoesDoKit::devolverConfigAoEnv()`.
         */
        if ($this->option('force')) {
            ConfiguracoesDoKit::devolverConfigAoEnv();
        }

        /*
         * `--custom` sai daqui, e é o ponto: ele existe para quem JÁ instalou.
         *
         * O `--force` refaz as cinco perguntas, mas apaga o SQLite antes (ver
         * `prepararBancoSqlite()`) — inócuo no minuto seguinte à instalação, destrutivo depois.
         * Este ramo cobre o "depois" sem tocar em banco, seeder nem asset.
         */
        if ($this->option('custom')) {
            return $this->customizarSemBanco();
        }

        $this->customizar();
        $this->gerarAppKey();
        $this->prepararBancoSqlite();
        $this->conferirConexao();

        if ($this->bancoAcessivel) {
            $this->migrar();
        }

        if ($this->bancoAcessivel && ! $this->option('no-seed')) {
            $this->semear();
            $this->formatarCodigoGerado();
        }

        $this->publicarAssets();

        if (! $this->option('no-npm')) {
            $this->construirFrontend();
        }

        if ($this->option('create-project')) {
            $this->desvincularDoSnyk();
        }

        $this->oferecerHostLocal();

        $this->banner();
        $this->resumoDaCustomizacao();
        $this->oferecerTestes();
        $this->oferecerEstrela();

        return self::SUCCESS;
    }

    /**
     * Há uma pessoa do outro lado capaz de responder?
     *
     * É a MESMA expressão que o Laravel usa para decidir se os prompts são
     * interativos (`ConfiguresPrompts::configurePrompts()`), e cada termo dela
     * existe por um motivo:
     *
     *   - `isInteractive()` sozinho NÃO basta. No Windows o Symfony não tem
     *     `posix_isatty` para consultar, então ele deixa a entrada como
     *     interativa mesmo quando não há terminal nenhum. Foi o que fez a
     *     instalação sem TTY "responder" as cinco perguntas com os defaults e
     *     reescrever o .env — trocando inclusive o APP_NAME pelo nome da pasta.
     *   - `stream_isatty(STDIN)` sozinho também não: sob `$this->artisan()` o
     *     STDIN não é tty, e o customizador se pularia dentro da própria suíte.
     *   - `runningUnitTests()` é o que reconcilia os dois.
     *
     * A propriedade equivalente do `Laravel\Prompts\Prompt` é `protected`, então
     * a expressão é repetida aqui em vez de lida de lá.
     */
    private function temTerminal(): bool
    {
        return ($this->input->isInteractive() && defined('STDIN') && stream_isatty(STDIN))
            || $this->laravel->runningUnitTests();
    }

    /**
     * O caminho do `--custom`: reescreve nome e cor, e diz o que ele NÃO faz.
     *
     * O aviso final não é rodapé: é o que impede alguém de concluir que "refazer as perguntas"
     * cobre as cinco. As outras três exigem recriar o banco, cada uma por um motivo diferente —
     * e o das credenciais é o menos obvio, porque o `UsuarioAdminSeeder` faz `firstOrCreate` por
     * e-mail: mudar o endereço e semear de novo criaria um SEGUNDO `master_global`, com o
     * primeiro vivo e a senha antiga.
     */
    private function customizarSemBanco(): int
    {
        $customizador = new CustomizadorDaInstalacao;
        $respostas    = $customizador->perguntarSemBanco();

        if ($respostas === null) {
            $this->components->info('Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('<fg=gray>Yang berubah</>', '');

        foreach ($customizador->aplicarSemBanco($respostas) as [$rotulo, $valor]) {
            $this->components->twoColumnDetail($rotulo, $valor);
        }

        $this->newLine();
        $this->components->warn('Yang TIDAK dilakukan perintah ini, dan ke mana masing-masing:');
        $this->components->bulletList([
            'Database — mengganti setelah migrate adalah instalasi lain: `php artisan kit:install --force` (MENGHAPUS data).',
            'Multi-organisasi — tabel permission hanya lahir dengan kolom konteks sebelum migrate: jalankan `php artisan kit:tenancy` (membuat ulang database).',
            'Kredensial administrator — `php artisan kit:admin` (atau layar profil panel). Menanam ulang TIDAK berlaku: seeder menjamin administrator ada, bukan bahwa ia mencerminkan .env.',
        ]);

        $this->components->info('Jalankan `php artisan config:clear` jika config Anda di-cache.');

        return self::SUCCESS;
    }

    /**
     * As perguntas — só num projeto nascendo, ou numa reinstalação explícita.
     *
     * A decisão inteira vive em `CustomizadorDaInstalacao::devePerguntar()`, com
     * o porquê de cada sinal. Aqui só se colhe o que é do comando.
     */
    private function customizar(): void
    {
        $customizador = new CustomizadorDaInstalacao;
        $respostas    = $customizador->perguntar($this, $this->temTerminal());

        if ($respostas === null) {
            $this->avisarSePerdeuAsPerguntas();

            return;
        }

        $this->resumo = $customizador->aplicar($respostas);
    }

    /**
     * Projeto novo + sem terminal = as perguntas passaram batido.
     *
     * Acontece de verdade, e não é hipótese: o Composer só repassa o terminal ao
     * script quando ele mesmo consegue (`ProcessExecutor::executeTty`), e em
     * várias combinações de sistema e console isso não acontece — o `artisan`
     * roda com a entrada fechada e todo prompt é pulado. Sem esta mensagem o
     * usuário conclui que a feature não existe; com ela, sabe o comando que
     * refaz a instalação **com** as perguntas.
     */
    private function avisarSePerdeuAsPerguntas(): void
    {
        if ($this->option('no-custom') || $this->temTerminal() || filled(config('app.key'))) {
            return;
        }

        $this->avisos[] = 'Terinstal dengan default: terminal ini tidak menerima pertanyaan '
            .'(di Windows Composer tidak pernah meneruskan terminal ke skrip). Untuk memilih lima '
            .'SEKARANG, saat database masih kosong: php artisan kit:install --force (membuat ulang database — '
            .'tidak berbahaya saat ini, destruktif setelahnya). Hanya nama dan warna, tanpa menyentuh database, kapan '
            .'saja: php artisan kit:install --custom';
    }

    /**
     * Confere o banco escolhido antes de migrar.
     *
     * Postgres e MySQL dependem de um serviço que pode não estar de pé — no caso
     * do Postgres esse é o caso NORMAL logo depois da instalação, porque o
     * container ainda não subiu. Sem esta conferência, `migrate` e `db:seed`
     * falhariam em cascata e o usuário receberia duas stack traces de PDO
     * dizendo a mesma coisa.
     */
    private function conferirConexao(): void
    {
        $driver = (string) config('database.default');

        if ($driver === 'sqlite') {
            return;
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->bancoAcessivel = false;

            $this->avisos[] = $driver === 'pgsql'
                ? 'PostgreSQL tidak merespons — melewati migrasi dan seeder. Jalankan servisnya lalu: docker compose up -d && php artisan migrate --seed'
                : 'MySQL tidak merespons — melewati migrasi dan seeder. Periksa kredensial di .env, buat database `'.config('database.connections.'.$driver.'.database').'` dan jalankan: php artisan migrate --seed';

            Log::warning(
                '[KitInstall@conferirConexao] Banco inacessível, migrations puladas | driver: '.$driver,
                ['driver' => $driver, 'exception' => $e],
            );
        }
    }

    protected function prepararEnv(): void
    {
        if (File::exists(base_path('.env'))) {
            return;
        }

        File::copy(base_path('.env.example'), base_path('.env'));
        $this->components->task('Membuat .env', fn (): bool => true);
    }

    protected function gerarAppKey(): void
    {
        if (filled(config('app.key'))) {
            return;
        }

        $this->callSilently('key:generate', ['--force' => true]);
        $this->components->task('Membangkitkan APP_KEY', fn (): bool => true);
    }

    /**
     * SQLite é o default do kit justamente para o create-project não depender
     * de serviço externo. Quem usa Postgres/Docker já trocou DB_CONNECTION.
     */
    protected function prepararBancoSqlite(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $caminho = config('database.connections.sqlite.database');

        if ($caminho === ':memory:') {
            return;
        }

        /*
         * `BancoSqlite` desconecta antes de apagar e FALHA se o arquivo sobreviver — no Windows,
         * arquivo aberto não se apaga, e o boot do kit já abre o SQLite. Sem isso o `--force` migrava
         * o banco velho e o resumo mentia sobre o login inicial. Ver o docblock da classe.
         */
        if ($this->option('force')) {
            BancoSqlite::recriar($caminho);
            $criado = true;
        } else {
            $criado = BancoSqlite::criarSeFaltar($caminho);
        }

        if ($criado) {
            $this->components->task('Membuat database SQLite', fn (): bool => true);
        }
    }

    protected function migrar(): void
    {
        $this->components->task('Menjalankan migrasi', function (): bool {
            $codigo = $this->callSilently('migrate', [
                '--graceful' => true,
                '--force'    => true,
            ]);

            if ($codigo !== self::SUCCESS) {
                $this->avisos[] = 'Migrasi tidak selesai. Jalankan: php artisan migrate';
            }

            return $codigo === self::SUCCESS;
        });
    }

    protected function semear(): void
    {
        $this->components->task('Mengisi role, permission, dan user awal', function (): bool {
            $codigo = $this->callSilently('db:seed', ['--force' => true]);

            if ($codigo !== self::SUCCESS) {
                $this->avisos[] = 'Seeder tidak selesai. Jalankan: php artisan db:seed';
            }

            return $codigo === self::SUCCESS;
        });
    }

    /**
     * Formata o que os geradores cuspiram.
     *
     * O `shield:generate` (chamado pelo ShieldPermissionsSeeder) escreve as
     * policies com o estilo dele, não com o do projeto — e aí `composer test`
     * falha no Pint logo na primeira execução de um projeto recém-criado.
     *
     * Pint é require-dev: numa instalação `--no-dev` ele não existe, e aí não
     * há o que formatar (nem `composer test` para rodar).
     */
    protected function formatarCodigoGerado(): void
    {
        $pint = base_path('vendor/bin/pint');

        if (! File::exists($pint)) {
            return;
        }

        $this->components->task('Memformat kode yang dibuat', function () use ($pint): bool {
            $processo = new Process([PHP_BINARY, $pint, '--quiet', 'app/Policies'], base_path(), timeout: 300);
            $processo->run();

            return $processo->isSuccessful();
        });
    }

    protected function publicarAssets(): void
    {
        $this->components->task('Menerbitkan aset Filament', function (): bool {
            $this->callSilently('filament:assets');
            $this->callSilently('storage:link');

            return true;
        });
    }

    /**
     * npm é opcional: quem só vai rodar a API/painel sem tocar em CSS pode
     * instalar sem Node. Sem build, o Filament ainda funciona com os assets
     * publicados acima; só os temas customizados via Vite ficam pendentes.
     */
    protected function construirFrontend(): void
    {
        $npm = (new ExecutableFinder)->find('npm');

        if ($npm === null) {
            $this->avisos[] = 'npm tidak ditemukan — melewati aset. Jalankan nanti: npm install && npm run build';

            return;
        }

        foreach ([['install'], ['run', 'build']] as $argumentos) {
            $rotulo = 'npm '.implode(' ', $argumentos);

            $this->components->task($rotulo, function () use ($npm, $argumentos, $rotulo): bool {
                $processo = new Process([$npm, ...$argumentos], base_path(), timeout: 900);
                $processo->run();

                if (! $processo->isSuccessful()) {
                    $this->avisos[] = "`{$rotulo}` gagal — jalankan manual untuk melihat error.";
                }

                return $processo->isSuccessful();
            });
        }
    }

    /**
     * Oferece o domínio local — a última pergunta, e de propósito ANTES do banner.
     *
     * "No final" aqui é o final do TRABALHO, não a última linha do `handle()`: o
     * `banner()` imprime as URLs de acesso a partir de `config('app.url')`, e
     * oferecer depois dele deixaria na tela `http://localhost:8000/app` logo
     * abaixo do endereço que a pessoa acabou de escolher — a feature funcionando
     * e parecendo não ter funcionado.
     *
     * Nada aqui aborta a instalação: o que não der certo vira aviso, impresso
     * pelo próprio `banner()` junto com os demais. Por isso a etapa também não
     * depende de flag nenhuma — `--force` apaga o banco, e amarrar o cadastro de
     * um DNS local a ele seria pedir para apagar o banco para ganhar um domínio.
     */
    private function oferecerHostLocal(): void
    {
        $aviso = (new HostLocal(base_path()))->oferecer($this->temTerminal());

        if ($aviso !== null) {
            $this->avisos[] = $aviso;
        }
    }

    protected function banner(): void
    {
        $url = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->components->info('Selesai! Proyek telah diinstal.');

        $this->components->bulletList([
            "Bisnis:          {$url}/app",
            "Administrasi:    {$url}/admin",
            "Infrastruktur:   {$url}/infra",
        ]);

        note(
            'Login awal: '.config('kit.admin.email')
            .' / '.config('kit.admin.password')
            ."\nGanti password sebelum lingkungan ini dibuka ke luar."
        );

        $this->components->bulletList([
            'Jalankan server dengan: composer dev',
            'Layanan opsional (Postgres, Redis, AI lokal): docker compose up -d',
        ]);

        foreach ($this->avisos as $aviso) {
            $this->components->warn($aviso);
        }

        $this->newLine();

        Log::info(
            '[KitInstall@banner] Instalação concluída | avisos: '.count($this->avisos),
            ['avisos' => $this->avisos, 'customizado' => $this->resumo !== []],
        );
    }

    /**
     * O que foi customizado — e o que continua manual.
     *
     * A segunda metade é o que fecha a lista de "Personalize seu projeto" do
     * README: sete itens que são código ou dado de tela e não cabem num prompt.
     * Se este resumo encolher, eles deixam de ser descobertos.
     */
    /**
     * Apaga o vínculo com o Snyk do kit — que é do kit, e não de quem instala.
     *
     * O porquê de cada arquivo está em `App\Support\VinculoDoSnyk`. O que importa AQUI é
     * quando isto roda, e a resposta é: só quando o `post-create-project-cmd` pede.
     *
     * Este comando roda nos DOIS lados — no `composer create-project` de quem instala e, à
     * mão, dentro do repositório do kit: o job `instalacao` do CI faz exatamente isso, e
     * `composer setup` é o que se roda depois de clonar. Apagar sempre destruiria os arquivos
     * da própria fonte e sujaria a árvore no CI.
     *
     * Quem sabe a diferença é o CHAMADOR, não o comando. Então o `post-create-project-cmd`
     * passa `--create-project`, e mais ninguém passa — nem o `setup`, que é de clone. Sem
     * heurística de `.git` ou de nome de pasta: essas erram, um flag não. Há caso de teste
     * guardando os dois scripts do `composer.json`, porque escrever o flag no `setup` por
     * engano é fácil e o estrago é exatamente o que este desenho evita.
     */
    private function desvincularDoSnyk(): void
    {
        $apagados = VinculoDoSnyk::remover(base_path());

        if ($apagados === []) {
            return;
        }

        $this->components->task(
            'Menghapus tautan Snyk kit ('.count($apagados).' berkas)',
            fn (): bool => true,
        );
    }

    private function resumoDaCustomizacao(): void
    {
        if ($this->resumo === []) {
            return;
        }

        $this->components->info('Yang dikustomisasi pada instalasi ini:');

        foreach ($this->resumo as [$item, $valor]) {
            $this->components->twoColumnDetail("<fg=gray>{$item}</>", $valor);
        }

        $this->newLine();
        $this->components->info('Yang masih harus disesuaikan manual:');
        $this->components->bulletList(CustomizadorDaInstalacao::itensManuais());
        $this->newLine();
    }

    /**
     * Oferece rodar a suíte do kit.
     *
     * Seguro por construção: o `phpunit.xml` fixa `DB_CONNECTION=sqlite` e
     * `DB_DATABASE=:memory:`, então a suíte não toca o banco recém-instalado nem
     * depende do Postgres estar de pé.
     */
    private function oferecerTestes(): void
    {
        if ($this->resumo === [] || ! $this->temTerminal()) {
            return;
        }

        if (! confirm('Jalankan test kit sekarang?', default: false)) {
            note('Kapan pun Anda ingin memeriksa fondasi: composer test:kit');

            return;
        }

        /*
         * Seleção por SUÍTE, e não `--group=kit`.
         *
         * O `pest-plugin-browser` sobe o Playwright na COLETA, ao parsear
         * qualquer arquivo com `visit()` — antes de qualquer filtro de grupo ser
         * consultado (`UsesBrowserTestCaseMethodFilter.php:57-60`). Num projeto
         * recém-instalado os browsers do Playwright não foram baixados, e
         * `--group=kit` morreria em `PlaywrightNotInstalledException` sem rodar um
         * único teste. É a mesma correção que o CI do kit já carrega.
         */
        $processo = new Process(
            [PHP_BINARY, 'artisan', 'test', '--testsuite=Kit,Tenancy'],
            base_path(),
            timeout: 900,
        );

        $processo->run(fn (string $tipo, string $saida) => $this->output->write($saida));
    }

    /**
     * O convite da estrela, no modelo do `Thanks` do Pest.
     *
     * O endereço é impresso SEMPRE, inclusive sem terminal: quem lê o output de
     * uma instalação automatizada também merece saber onde o kit mora. A
     * pergunta é que só existe com terminal, e `--no-support` a desliga.
     */
    private function oferecerEstrela(): void
    {
        $this->components->twoColumnDetail('<fg=gray>Repositori kit</>', self::REPOSITORIO);

        if ($this->option('no-support') || ! $this->temTerminal()) {
            return;
        }

        if (! confirm('Suka? Beri bintang pada starter-kit di GitHub?', default: false)) {
            return;
        }

        // ponytail: `exec` do SO é o mesmo caminho do Pest; falha silenciosa em
        // servidor sem ambiente gráfico é aceitável — o endereço já foi impresso.
        match (PHP_OS_FAMILY) {
            'Darwin'  => exec('open '.self::REPOSITORIO),
            'Windows' => exec('start '.self::REPOSITORIO),
            'Linux'   => exec('xdg-open '.self::REPOSITORIO),
            default   => null,
        };
    }
}

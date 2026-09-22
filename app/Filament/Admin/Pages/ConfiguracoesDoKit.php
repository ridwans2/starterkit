<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\ExigePermissaoDaTela;
use App\Settings\ConfiguracoesDoKit as SettingsDoKit;
use App\Support\CustomizadorDaInstalacao;
use App\Support\DensidadeDoLayout;
use App\Support\Paineis;
use App\Support\ProvedorAntiRobo;
use App\Support\ProvedorSocial;
use App\Support\TetoDeUpload;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

/**
 * A tela das configurações da INSTALAÇÃO, em /admin/configuracoes-da-aplicacao.
 *
 * O que era pergunta do `kit:install` gravada no `.env`, mais a arte do login
 * (que era edição de arquivo à mão) e os defaults de tabela (que eram um TODO no
 * `ConfiguraFilamentGlobal`), passam a ser alteráveis aqui. O valor gravado vence
 * o `.env` em tempo de execução — ver o docblock de `App\Settings\ConfiguracoesDoKit`
 * e ADR-01 da wiki `settings-do-kit`.
 *
 * **Isto não é o settings de uma organização.** A identidade visual de um tenant
 * é CRUD em /admin/organizacoes, e ela vence esta dentro de /app/{slug}.
 *
 * ## Autorização: uma permissão só
 *
 * `ExigePermissaoDaTela` — e não `HasPageShield` direto — porque é a convenção do
 * kit e porque ela é à prova do defeito silencioso: método definido na classe vence
 * método vindo de trait, sem erro nem aviso, então o dia em que esta Page ganhar um
 * `canAccess()` próprio (uma flag de config, por exemplo) a permissão deixaria de
 * ser consultada e o diff pareceria correto. Com o trait do kit, a regra local vai
 * no hook `regraLocalDeAcesso()` e as duas convivem. Esta tela não tem regra local
 * hoje, e por isso não sobrescreve o hook.
 *
 * A permission é `View:ConfiguracoesDoKit`, que o `ShieldPermissionsSeeder` gera e o
 * `PapeisSeeder` entrega ao papel `admin` junto com o resto da matriz do painel —
 * nenhuma lista precisou ser editada.
 *
 * `canEdit()` devolve `canAccess()`: uma permissão governa abrir e salvar. Um
 * papel "só leitura" aqui seria um papel que LÊ a senha de SMTP, porque o
 * `canEdit()` do plugin não esconde valor nenhum — o README do vendor diz isso
 * por escrito, e o código confirma (`save()` faz `if (! $this->canEdit()) return;`
 * e `defaultForm()` faz `->disabled(! $this->canEdit())`, nenhum dos dois oculta).
 * Ver ADR-04.
 *
 * **Se esta Page for movida para o painel `app`**, ela PRECISA entrar em
 * `PapeisSeeder::permissoesDeAdministracaoDoApp()`, senão todo `panel_user` herda
 * a configuração da instalação, sem erro nenhum. Ver `.ai/rules/filament.md`.
 *
 * ## Por que este arquivo foi escrito à mão
 *
 * `php artisan make:filament-settings-page ConfiguracoesDoKit ConfiguracoesDoKit`
 * **falha**: o gerador do plugin monta os imports em
 * `SettingsPageClassGenerator::getImports()` e, quando o basename da classe de
 * settings é igual ao da Page, cai num `PhpNamespace::addUse("")` e estoura.
 * Daí o alias `SettingsDoKit` no `use` acima — e o registro deste parágrafo, para
 * a próxima pessoa não perder tempo com o comando.
 */
class ConfiguracoesDoKit extends SettingsPage
{
    use ExigePermissaoDaTela;

    protected static string $settings = SettingsDoKit::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public function getTitle(): string
    {
        return __('Application settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Application settings');
    }

    /**
     * Sem isto o Filament deriva o slug do NOME DA CLASSE
     * (`vendor/filament/filament/src/Pages/Concerns/HasRoutes.php:getDefaultSlug()`), e a URL
     * ficaria `configuracoes-do-kit` — o nome que a tela deixou de usar no menu e no título.
     * Renomear a classe arrastaria `App\Settings\ConfiguracoesDoKit`, o listener de auditoria e
     * a permissão `page_ConfiguracoesDoKit` já gravada nas instalações; o `$slug` resolve só a
     * URL. O endereço antigo continua atendido por um redirect no `KitServiceProvider`.
     */
    protected static ?string $slug = 'configuracoes-da-aplicacao';

    protected static ?int $navigationSort = 90;

    /**
     * Uma permissão para abrir e para salvar.
     *
     * Método de **instância**, não estático: é assim que o vendor declara
     * (`vendor/filament/spatie-laravel-settings-plugin/src/Pages/SettingsPage.php:248`),
     * e o exemplo do README dele induz ao erro por mostrar `canAccess()` estático
     * logo acima. Declarar `static` aqui não sobrescreveria nada.
     */
    public function canEdit(): bool
    {
        return static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('configuracoes')
                    ->persistTabInQueryString()
                    // Sem isto o componente de abas ocupa UMA das duas colunas do
                    // `defaultForm()` e o resto do formulário sai ao lado dele.
                    ->columnSpanFull()
                    ->tabs([
                        $this->abaIdentidade(),
                        $this->abaEmail(),
                        $this->abaTabelas(),
                        $this->abaRegistro(),
                        $this->abaLogin(),
                        $this->abaKit(),
                    ]),
            ]);
    }

    /**
     * Registra QUEM salvou. O que mudou fica na trilha de `audits`.
     *
     * Nada é logado na abertura da tela: um `info` por request é o ruído que a
     * nota do canal `autenticacao` em `config/logging.php` mediu em 1,1 MB/dia.
     */
    protected function afterSave(): void
    {
        Log::channel('configuracoes')->info(
            '[ConfiguracoesDoKit@afterSave] Configurações da aplicação salvas | usuario: '.auth()->id(),
            ['user_id' => auth()->id(), 'campos' => array_keys($this->data ?? [])],
        );
    }

    /**
     * O segredo não entra no formulário — e por isso não entra no HTML.
     *
     * Este é o primeiro dos dois pontos que impedem o vazamento descrito no campo
     * `mail_password`. O `fillForm()` do plugin faz `$this->form->fill($data)` com o
     * `toArray()` do settings, e o que vai para `$data` vai para o `wire:snapshot`. Zerar aqui é
     * o que garante que o valor guardado nunca é serializado para o navegador — nem para o
     * administrador que abriu a tela.
     *
     * Zerar aqui NÃO apaga a senha: o segundo ponto (`->dehydrated()`) mantém a chave fora do
     * save quando o campo fica em branco, e o `$settings->fill()` do plugin só aplica as chaves
     * presentes (`vendor/filament/spatie-laravel-settings-plugin/src/Pages/SettingsPage.php:83`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['mail_password'] = null;

        /*
         * Todos os `client_secret`, por laço sobre o enum. Zerar por laço em vez de listar à mão
         * é o que impede o defeito de esquecer UM provedor: o campo esquecido continuaria
         * serializando o segredo gravado no `wire:snapshot`, com 200 e sem clique em "revelar",
         * e nenhum caso de teste dos outros provedores acusaria. Ver `.ai/rules/pages.md`.
         */
        foreach (ProvedorSocial::cases() as $provedor) {
            $data[$provedor->propriedadeDeSettings('client_secret')] = null;
        }

        // A chave secreta do anti-robô: mesmo segredo, mesmos dois pontos (`.ai/rules/pages.md`).
        $data['login_anti_robo_chave_secreta'] = null;

        /*
         * A densidade entra COAGIDA, e isso trava a tela inteira se faltar.
         *
         * `Select` acrescenta sozinho um `Rule::in()` das próprias opções. Um nível ilegível
         * gravado na linha de settings — `compact` em vez de `compacto`, vindo de edição à mão
         * ou de versão futura revertida — nasceria no estado do formulário e seria recusado pela
         * validação. O efeito NÃO fica contido no campo: a tela para de salvar por inteiro, e
         * quem tentar mudar o nome da aplicação leva erro num campo que não tocou. É o mesmo
         * defeito que `comValorConfigurado()` descreve para `MAIL_MAILER=ses`, logo abaixo.
         *
         * `coagir()` e NÃO `comValorConfigurado()`, porque os dois problemas só se parecem:
         * `ses` é um transporte legítimo fora da lista curta, e rebaixá-lo ao default seria perda
         * de dado. Nível de densidade tem vocabulário FECHADO — `compact` não é válido em lugar
         * nenhum, é lixo, e oferecê-lo como opção marcada exibiria lixo e o gravaria de volta.
         *
         * `coagir()` é a mesma função que o render hook usa, então a tela e a página servida
         * respondem a mesma coisa para a mesma entrada. Coberto por CT-16; CT-11 é a metade do
         * render.
         */
        $data['densidade_do_layout'] = DensidadeDoLayout::coagir($data['densidade_do_layout'] ?? null)->value;

        return $data;
    }

    /**
     * As opções do campo, mais o valor que JÁ está configurado, quando ele não está na lista.
     *
     * `Select` acrescenta um `Rule::in()` das próprias opções sozinho
     * (`vendor/filament/forms/src/Components/Select.php:1742-1748`). Isso torna a tela
     * **impossível de salvar** — nem o nome da aplicação grava — sempre que o valor vindo do
     * `.env` é legítimo mas está fora da lista curta que a tela oferece. E isso não é hipótese:
     * `config/mail.php` tem 9 transportes e a tela oferece 3; `Color` tem 26 cores e a lista
     * fechada do kit tem 16. Quem instalou com `MAIL_MAILER=ses` abria a tela e não conseguia
     * mudar nada.
     *
     * As duas saídas óbvias são piores. Ampliar a lista para tudo transforma a tela num
     * formulário de tudo-que-o-Laravel-suporta. Normalizar o valor para o default rebaixa, em
     * silêncio, o transporte de produção de alguém no primeiro salvamento — perda de dado
     * disfarçada de validação.
     *
     * Então o valor configurado entra como opção, marcado. A lista curta continua guiando quem
     * escolhe; quem já tem algo fora dela vê o que tem, mantém se quiser, e consegue salvar o
     * resto da tela.
     *
     * @param  array<string, string>  $opcoes
     * @return array<string, string>
     */
    private function comValorConfigurado(array $opcoes, ?string $atual, string $rotulo): array
    {
        if (blank($atual) || array_key_exists($atual, $opcoes)) {
            return $opcoes;
        }

        return $opcoes + [$atual => $atual.' — '.$rotulo];
    }

    /**
     * Existe segredo guardado deste provedor? — para o placeholder dizer "em branco mantém".
     *
     * Lê do settings e não do formulário, justamente porque o formulário não o tem (ele é zerado
     * em `mutateFormDataBeforeFill()`). Devolve o valor e não um booleano só para o chamador
     * poder usar `filled()`; o valor não é exibido em lugar nenhum.
     */
    private function segredoGuardadoDe(ProvedorSocial $provedor): ?string
    {
        $propriedade = $provedor->propriedadeDeSettings('client_secret');

        return app(static::getSettings())->{$propriedade};
    }

    /**
     * Existe senha de SMTP guardada? — para o placeholder dizer "em branco mantém".
     *
     * Lê do settings e não do formulário, justamente porque o formulário não a tem. Devolve o
     * valor e não um booleano só para o chamador poder usar `filled()`; o valor não é exibido em
     * lugar nenhum.
     */
    private function senhaDeSmtpGuardada(): ?string
    {
        return app(static::getSettings())->mail_password;
    }

    /** Existe chave secreta do anti-robô guardada? — para o placeholder dizer "em branco mantém". */
    private function chaveSecretaAntiRoboGuardada(): ?string
    {
        return app(static::getSettings())->login_anti_robo_chave_secreta;
    }

    private function abaIdentidade(): Tab
    {
        return Tab::make(__('Identity'))
            ->icon('heroicon-o-paint-brush')
            ->schema([
                TextInput::make('nome_da_aplicacao')
                    ->label(__('Application name'))
                    ->helperText(__('Shows at the top of the three panels, in the tab title and as the default sender.'))
                    ->required()
                    ->maxLength(255),

                /*
                 * A versão do SISTEMA, não a do kit. Texto livre: esquema de versionamento é
                 * decisão do projeto (SemVer, data, número de build), e validar formato aqui
                 * seria o kit escolhendo pelo projeto. Vazio some com a versão do rodapé.
                 */
                TextInput::make('versao_do_sistema')
                    ->label(__('System version'))
                    ->helperText(__('The version of YOUR product, shown in the panel footer. When blank, the footer shows no version. APP_VERSION in the .env seeds this field at install time; after that this is where you change it — the stored value beats the file, even when it is empty.'))
                    ->placeholder('1.0.0')
                    ->maxLength(50),

                Select::make('cor_primaria')
                    ->label(__('Primary color'))
                    ->helperText(__('The Filament palette. Leave blank for the default (amber).'))
                    // A lista fechada do kit, e não reflection sobre `Color`: aquela
                    // classe também expõe constantes que não são cor (`WCAG_AA_TEXT`)
                    // e neutros que ninguém escolhe como primária.
                    ->options(fn (): array => $this->comValorConfigurado(
                        array_combine(CustomizadorDaInstalacao::CORES, CustomizadorDaInstalacao::CORES),
                        app(static::getSettings())->cor_primaria,
                        'fora da lista do kit',
                    ))
                    ->placeholder(__('Filament default (amber)')),

                ColorPicker::make('cor_primaria_hex')
                    ->label(__('Free primary color'))
                    ->helperText(__('Brand color in hexadecimal. WINS over the selection above when filled in. An invalid value is ignored.'))
                    ->regex('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'),

                $this->arquivo('logo', __('Brand logo'), __('Replaces the name at the top of the panels. When blank, the name is used.')),

                $this->arquivo('favicon', __('Favicon'), __('The browser tab icon. When blank, the Filament one.')),

                $this->arquivo(
                    'arte_do_login',
                    __('Login screens artwork'),
                    __('The image beside the login, password recovery and e-mail confirmation screens. When blank, the artwork that ships with the kit.'),
                ),
            ]);
    }

    private function abaEmail(): Tab
    {
        $smtp = fn (Get $get): bool => $get('mail_mailer') === 'smtp';

        return Tab::make(__('Email'))
            ->icon('heroicon-o-envelope')
            ->schema([
                Select::make('mail_mailer')
                    ->label(__('Transport'))
                    ->helperText(__('`log` only writes to storage/logs — invitations and reminders reach nobody. Saving here applies to the next request; a running queue worker (`queue:work`) keeps the old configuration in memory — run `php artisan queue:restart` so queued emails leave through the new transport.'))
                    ->options(fn (): array => $this->comValorConfigurado(
                        [
                            'log'   => __('Log (does not send — writes to storage/logs)'),
                            'array' => __('Array (discards — testing only)'),
                            'smtp'  => 'SMTP',
                        ],
                        app(static::getSettings())->mail_mailer,
                        'configurado no .env',
                    ))
                    ->required()
                    ->live(),

                TextInput::make('mail_from_address')
                    ->label('Remetente')
                    ->email()
                    ->maxLength(255),

                TextInput::make('mail_from_name')
                    ->label(__('Sender name'))
                    ->maxLength(255),

                TextInput::make('mail_host')
                    ->label('Servidor')
                    ->maxLength(255)
                    ->visible($smtp),

                TextInput::make('mail_port')
                    ->label('Porta')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->visible($smtp),

                Select::make('mail_scheme')
                    ->label('Criptografia')
                    ->options(fn (): array => $this->comValorConfigurado(
                        ['tls' => 'TLS', 'ssl' => 'SSL'],
                        app(static::getSettings())->mail_scheme,
                        'configurado no .env',
                    ))
                    ->placeholder(__('None'))
                    ->visible($smtp),

                TextInput::make('mail_username')
                    ->label(__('User'))
                    ->maxLength(255)
                    ->visible($smtp),

                /*
                 * A senha NUNCA e hidratada — e `->password()` nao resolve isso.
                 *
                 * `->password()` e `->revealable()` mexem no `type` do input, ou seja, na TELA.
                 * O valor continua em `$this->data`, que e propriedade publica da Page
                 * (`vendor/filament/spatie-laravel-settings-plugin/src/Pages/SettingsPage.php:33`),
                 * e o Livewire serializa `$data` inteiro no `wire:snapshot` do HTML. Resultado
                 * medido pelo quality gate: `GET /admin/configuracoes-da-aplicacao` devolvia a senha em
                 * claro no corpo da resposta, com 200 e sem clique em "revelar".
                 *
                 * Por isso a barreira e em DOIS pontos, e nenhum deles e visual:
                 *
                 * 1. `mutateFormDataBeforeFill()` zera a chave antes de o formulario ser
                 *    preenchido — o segredo nao entra em `$data`, logo nao entra no snapshot;
                 * **O parametro da closure PRECISA se chamar `$state`.** O Filament resolve
                 * dependencia de closure por NOME
                 * (`vendor/filament/schemas/src/Components/Component.php:87-98`), e nome
                 * desconhecido com tipo escalar nao resolve para nada
                 * (`vendor/filament/support/src/Concerns/EvaluatesClosures.php:143-160`). Com
                 * `$estado`, a closure recebia `null`, `filled(null)` era sempre `false` e a
                 * chave NUNCA chegava ao save: a senha de SMTP era impossivel de gravar pela
                 * tela, em silencio, desde a v0.19.0.
                 *
                 * O par de casos que a rule `.ai/rules/pages.md` pede — "nao aparece no HTML" e
                 * "sobrevive a um save que nao o tocou" — passa com esse defeito, porque os dois
                 * afirmam o que NAO acontece. Falta o terceiro: campo PREENCHIDO grava. Foi ele
                 * que pegou.
                 *
                 * 2. `->dehydrated()` so deixa a chave chegar ao save quando o campo foi
                 *    preenchido. Ausente do `$data` do save, o `$settings->fill()` do plugin nao
                 *    mexe no valor guardado (ele aplica so as chaves presentes), e a senha atual
                 *    sobrevive a um salvamento em que ninguem a tocou.
                 *
                 * `->revealable()` fica: agora o que ele revela e o que a PESSOA acabou de
                 * digitar, que e conferencia de digitacao, nao exposicao do que estava gravado.
                 */
                TextInput::make('mail_password')
                    ->label(__('Password'))
                    ->helperText(__('Stored encrypted. Leave blank to keep the current password — it is not displayed here, not even in the page source. The audit trail records that it changed, never the value.'))
                    ->placeholder(fn (): string => filled($this->senhaDeSmtpGuardada()) ? __('Already configured — blank keeps it') : __('No password configured'))
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->visible($smtp),
            ]);
    }

    private function abaTabelas(): Tab
    {
        return Tab::make('Tabelas')
            ->icon('heroicon-o-table-cells')
            ->schema([
                TextInput::make('paginacao_padrao')
                    ->label(__('Rows per page'))
                    ->helperText(__('The default of EVERY table in the three panels, including third-party packages.'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    /*
                     * Teto de 100 para quem ESCOLHE, e o valor já configurado quando ele é maior.
                     * `NumeroDoEnv::positivo()` não tem teto, então `KIT_PAGINACAO=500` é estado
                     * alcançável — e um `maxValue(100)` fixo travaria a tela inteira por causa de
                     * um número que a pessoa nem veio mexer. Mesmo raciocínio de
                     * `comValorConfigurado()`.
                     */
                    ->maxValue(fn (): int => max(100, (int) app(static::getSettings())->paginacao_padrao))
                    ->required(),

                Toggle::make('tabela_listrada')
                    ->label('Linhas listradas')
                    ->helperText(__('The only visual density control Filament 5 offers — there is no table density API in this version.')),

                Toggle::make('persistir_filtros')
                    ->label(__('Remember filter, search and sorting'))
                    ->helperText(__('The user\'s selection survives navigation, kept in the session.')),

                Toggle::make('colunas_redimensionaveis')
                    ->label(__('Draggable columns'))
                    ->helperText(__('Drag to resize columns. No effect if the resized-column package is removed.')),
            ]);
    }

    /**
     * A porta de entrada do painel /app — fechada por default.
     *
     * As três chaves governam `App\Support\RegistroAberto`, e chegam lá pelo
     * `mapaDeConfiguracao()`: a classe lê `config('kit.registro.*')` e o
     * `aplicarNaConfig()` sobrepõe essa config com o que está gravado aqui. Nenhuma linha
     * daquela classe precisou mudar.
     *
     * "Cadastro nasce pendente" fica OCULTO com o registro desligado, e isso não é estética:
     * aprovação de cadastro não significa nada sem porta aberta, e toggle que não faz efeito é
     * pior que toggle ausente — a pessoa acha que configurou algo.
     *
     * "Exigir e-mail validado" NÃO segue a mesma regra, e a diferença é medida: a exigência
     * alcança todo usuário do /app, venha ele de cadastro aberto, de convite ou da tela de
     * usuários. Esconder o campo com o registro fechado produziria o defeito espelhado —
     * exigência LIGADA e invisível, com quem administra sem como desligá-la pela tela. Ver a
     * revisão adversarial na wiki `verificacao-de-email-editavel`.
     */
    private function abaRegistro(): Tab
    {
        $aberto = fn (Get $get): bool => (bool) $get('registro_habilitado');

        return Tab::make(__('Sign-up'))
            ->icon('heroicon-o-user-plus')
            ->schema([
                Toggle::make('registro_habilitado')
                    ->label(__('Allow sign-up without an invitation in /app'))
                    ->helperText(__('When off, /app only accepts people with an invitation — the kit default. When on, the registration screen also accepts visitors, and each organization still decides whether it accepts yours (in /admin/organizacoes).'))
                    ->live(),

                Toggle::make('registro_aprovacao_manual')
                    ->label(__('New account starts pending approval'))
                    ->helperText(__('Anyone who registers gets no role until somebody approves in /admin/usuarios — and without a role no panel opens.'))
                    ->visible($aberto),

                /*
                 * A verificação de e-mail voltou a ser editável, e o que mudou não foi este
                 * arquivo: até a v0.19.3 um toggle aqui gravava e não fazia efeito, porque o
                 * `AppPanelProvider` lia a chave no BOOT e o middleware ficava fixado no array
                 * da rota (`.../Pages/Concerns/HasRoutes.php:91`). Agora a rota guarda um
                 * decisor — `App\Http\Middleware\ExigirEmailVerificado` —, que pergunta a cada
                 * request. Ver a wiki `verificacao-de-email-editavel`.
                 *
                 * O `helperText` avisa o que o README avisa, porque aqui um clique basta: a
                 * exigência alcança TODO usuário do /app, não só quem se cadastrar depois.
                 */
                Toggle::make('registro_verificar_email')
                    ->label('Exigir e-mail validado no /app')
                    ->helperText(__('On, anyone who has not confirmed their e-mail is taken to the confirmation screen when they enter /app — and that applies to EVERY user of the panel, not only new ones. In a base that already has people in it, first check who was created from the users screen (the README has the command). Anyone arriving by invitation is never affected: the token already proved they own the address.')),
            ]);
    }

    /**
     * Login social e o rodapé da tela de login.
     *
     * Estas nunca precisaram do decisor que `registro_verificar_email` precisou: as que decidem
     * algo são lidas por request — o `abort_unless()` do `LoginSocialController` e a closure do
     * render hook dos botões. Nada é decidido no boot do painel.
     *
     * Uma SEÇÃO por provedor, e não treze campos soltos: com quatro provedores são doze campos de
     * credencial, nove deles condicionais a um toggle acima. Solto, o campo que aparece empurra
     * os outros para baixo sem indicar de quem ele é. Ver ADR-07 da wiki
     * `mais-provedores-sociais`.
     *
     * As seções vêm de um laço sobre `ProvedorSocial::cases()`: provedor novo aparece na tela sem
     * ninguém tocar nesta tela.
     */
    private function abaLogin(): Tab
    {
        $secoes = array_map(
            fn (ProvedorSocial $provedor): Section => $this->secaoDoProvedor($provedor),
            ProvedorSocial::cases(),
        );

        return Tab::make('Login')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->schema([
                // Duas seções, dois assuntos: os provedores (um bloco fechado por provedor,
                // com o ícone de status no cabeçalho) e o rodapé. Pedido do solicitante na
                // validação real dos provedores (2026-08-26).
                Section::make(__('Single login page'))
                    ->description(__('One screen at /login for all three panels, instead of /admin/login, /infra/login and /app/login.'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('login_unificado')
                            ->label('Unificar o login em /login')
                            ->helperText(__('When off: each panel has its own login screen (Filament default). When on: the three screens lead to /login; anyone with access to more than one panel chooses which to open after signing in, anyone with only one goes straight in. Takes effect immediately, no deploy.'))
                            ->columnSpanFull(),
                    ]),
                Section::make('Login social')
                    ->description(__('One collapsed block per provider. The icon in the header tells whether the button is enabled; open it to see the credentials.'))
                    ->columnSpanFull()
                    ->schema([
                        /*
                         * Vale para os quatro provedores. Lida por request no callback, então pode
                         * viver aqui (`.ai/rules/settings.md`). ADR-03 de vinculo-de-provedor-social.
                         */
                        Toggle::make('login_vinculo_confirmar')
                            ->label(__('Require email confirmation on the first social sign-in to an existing account'))
                            ->helperText(__('When off: the person signs in and gets an email notice ("your account was accessed through Google for the first time"). When on: they get a 30-minute link and only gets in after confirming. On later sign-ins the account is recognized by the provider identity, in either mode.'))
                            ->columnSpanFull(),

                        ...$secoes,
                    ]),

                $this->secaoAntiRobo(),

                Section::make(__('Login screen footer'))
                    ->description(__('Shows at the bottom of the login screens of all three panels.'))
                    ->columnSpanFull()
                    ->schema([
                        /*
                         * Markdown, e não HTML: a tela de login é pública e não autenticada, e HTML
                         * cru ali seria XSS armazenado. O Markdown dá negrito, itálico e link — o que
                         * um rodapé precisa — e a view descarta qualquer HTML cru e qualquer link com
                         * esquema inseguro (`Str::markdown` com `html_input: strip`,
                         * `allow_unsafe_links: false`). A barra tem só esses botões de propósito:
                         * título, tabela e anexo não cabem num rodapé de duas linhas.
                         */
                        MarkdownEditor::make('login_rodape')
                            ->hiddenLabel()
                            ->helperText(__('Accepts Markdown (bold, italic, link). Raw HTML is discarded, because the login screen is public.'))
                            ->toolbarButtons([['bold', 'italic', 'strike', 'link']])
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * O desafio anti-robô das três telas públicas: interruptor, provedor e o par de chaves.
     *
     * Quem decide se a proteção está no ar é `ConfiguracaoDoLogin::antiRobo()`, com quatro
     * condições — interruptor, chave do site, chave secreta e provedor conhecido —, e o
     * `helperText` do toggle diz isso por escrito: ligar aqui sem as chaves não liga nada, e é de
     * propósito (um campo obrigatório que ninguém consegue preencher trancaria o login dos três
     * painéis). ADR-03 da wiki `recaptcha-nas-telas-publicas`.
     *
     * A chave secreta tem o tratamento da senha de SMTP e dos `client_secret`: zerada no fill e
     * dehidratada só quando preenchida (`.ai/rules/pages.md`). As opções do `Select` saem do enum,
     * que implementa `HasLabel` — o `Rule::in()` que o `Select` acrescenta sozinho casa com
     * `ProvedorAntiRobo::tryFrom()`, então não há valor que grave e não governe.
     */
    private function secaoAntiRobo(): Section
    {
        $ligado = fn (Get $get): bool => (bool) $get('login_anti_robo_habilitado');

        return Section::make(__('Anti-bot protection'))
            ->description(__('An "I\'m not a robot" challenge on the login, "forgot password?" and registration screens of all three panels. When off, the screens stay as they always were: no external script is loaded.'))
            ->collapsible()
            ->columnSpanFull()
            ->schema([
                Toggle::make('login_anti_robo_habilitado')
                    ->label(__('Require the anti-bot challenge on public screens'))
                    ->helperText(__('Turning this on does not put the button live by itself: the credentials below must also be filled in. Social login AUTHENTICATES people who already have an account — creating an account depends on open registration, in the previous tab.'))
                    ->live(),

                /*
                 * Só aparece com `APP_ENV=local` — a decisão que ele governa não existe fora dali.
                 *
                 * O toggle diz "aplicar TAMBÉM em ambiente local": em produção ou homologação ele
                 * é um interruptor sem efeito nenhum, e um interruptor inerte na tela é pior que
                 * ausente — quem o vê supõe que mexer nele muda alguma coisa. Quem decide o efeito
                 * é `ConfiguracaoDoLogin::antiRobo()`, que só consulta a chave quando
                 * `app()->isLocal()`.
                 *
                 * O valor GRAVADO não é tocado: quem ligou o toggle numa máquina local e subiu o
                 * mesmo banco para produção continua com `true` no banco, e continua sem efeito
                 * lá. Esconder o campo não apaga a escolha, só para de oferecê-la onde ela é
                 * inócua.
                 */
                Toggle::make('login_anti_robo_local')
                    ->label(__('Also apply in the local environment (APP_ENV=local)'))
                    ->helperText(__('Off, the challenge does not appear while the application runs with APP_ENV=local — a production key does not accept localhost, and the required field would be impossible to fill. Turn it on only to test with keys that accept localhost.'))
                    ->visible(fn (Get $get): bool => $ligado($get) && app()->isLocal()),

                Select::make('login_anti_robo_provedor')
                    ->label('Provedor')
                    ->options(array_reduce(ProvedorAntiRobo::cases(), function (array $carry, ProvedorAntiRobo $provedor): array {
                        $carry[$provedor->value] = $provedor->getLabel();

                        return $carry;
                    }, []))
                    ->helperText(function (Get $get): string {
                        $provedor = $get('login_anti_robo_provedor');

                        if (is_string($provedor) && $provedor !== '') {
                            return ProvedorAntiRobo::tryFrom($provedor)?->ondeCriarAsChaves()
                                ?? __('One provider at a time. reCAPTCHA v2 is the classic box; v3 is invisible and decides by score; Turnstile does not track and costs nothing.');
                        }

                        return __('One provider at a time. reCAPTCHA v2 is the classic box; v3 is invisible and decides by score; Turnstile does not track and costs nothing.');
                    })
                    ->required()
                    ->native(false)
                    ->live()
                    ->visible($ligado),

                TextInput::make('login_anti_robo_pontuacao_minima')
                    ->label(__('Minimum score (reCAPTCHA v3)'))
                    ->helperText(__('Google returns a score from 0 (robot) to 1 (person); below this value the submission is refused. 0.5 is the suggestion. Only reCAPTCHA v3 uses this.'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.05)
                    ->required()
                    ->dehydrateStateUsing(fn (mixed $state): float => (float) $state)
                    ->visible(fn (Get $get): bool => $ligado($get) && ProvedorAntiRobo::tryFrom((string) $get('login_anti_robo_provedor'))?->usaPontuacao() === true),

                TextInput::make('login_anti_robo_chave_do_site')
                    ->label(__('Site key'))
                    ->helperText(__('The public key: it goes into the page HTML.'))
                    ->maxLength(255)
                    ->visible($ligado),

                TextInput::make('login_anti_robo_chave_secreta')
                    ->label(__('Secret key'))
                    ->helperText(__('Stored encrypted. Leave blank to keep the current one — it is not shown here, nor in the page source.'))
                    ->placeholder(fn (): string => filled($this->chaveSecretaAntiRoboGuardada()) ? __('Already configured — blank keeps it') : __('No key configured'))
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->visible($ligado),
            ]);
    }

    /**
     * O bloco de um provedor de login social: o interruptor e as duas credenciais.
     *
     * O botão só entra no ar com o interruptor ligado E as três credenciais preenchidas, e é
     * `ConfiguracaoDoLogin::disponivel()` que decide — os campos aqui só alimentam a config que
     * ela lê. Com o interruptor desligado, `/auth/{provedor}/*` responde 404: esconder o botão
     * não é barreira, porque a URL é pública.
     *
     * `->columnSpanFull()` explícito na `Section`: `Grid`, `Section` e `Fieldset` NÃO ocupam todas
     * as colunas por default, e sem isto a seção fica numa das duas colunas do `defaultForm()`.
     *
     * O `client_secret` tem o mesmo tratamento da senha de SMTP, pelo mesmo motivo: `->password()`
     * esconde na TELA e o valor continua em `$this->data`, que o Livewire serializa no
     * `wire:snapshot`. São dois pontos, e nenhum é visual — o segredo é zerado no fill (por laço,
     * em `mutateFormDataBeforeFill()`) e só chega ao save quando preenchido (`->dehydrated()`).
     * Ver `.ai/rules/pages.md`.
     */
    private function secaoDoProvedor(ProvedorSocial $provedor): Section
    {
        $habilitado = $provedor->propriedadeDeSettings('habilitado');
        $ligado     = fn (Get $get): bool => (bool) $get($habilitado);

        return Section::make("Entrar com {$provedor->rotulo()}")
            ->description($this->ondeCriarOApp($provedor))
            // Fechada ao abrir a tela; o status vive no cabeçalho, então não precisa abrir para
            // saber. O interruptor é `live()`, e o ícone acompanha na hora.
            ->collapsed()
            ->icon(fn (Get $get): string => $ligado($get) ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor(fn (Get $get): string => $ligado($get) ? 'success' : 'gray')
            ->columnSpanFull()
            ->schema([
                Toggle::make($habilitado)
                    ->label(__('Enable the :provider button', ['provider' => $provedor->rotulo()]))
                    ->helperText(__('Switching this on does not put the button live by itself: the credentials below must also be filled in. Social login AUTHENTICATES people who already have an account — creating one depends on open registration, in the previous tab.'))
                    ->live(),

                TextInput::make($provedor->propriedadeDeSettings('client_id'))
                    ->label('Client ID')
                    // O caminho é o que vive em `config/services.php`, relativo de propósito —
                    // cadastre-o ABSOLUTO no console do provedor.
                    ->helperText("A URI de redirecionamento a cadastrar no provedor é o seu domínio + /auth/{$provedor->value}/callback")
                    ->maxLength(255)
                    ->visible($ligado),

                TextInput::make($provedor->propriedadeDeSettings('client_secret'))
                    ->label('Client Secret')
                    ->helperText(__('Stored encrypted. Leave blank to keep the current secret — it is not shown here, nor in the page source.'))
                    ->placeholder(fn (): string => filled($this->segredoGuardadoDe($provedor)) ? __('Already configured — leave blank to keep the current value') : 'Nenhum segredo configurado')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->visible($ligado),

                /*
                 * EM QUAIS PAINEIS este provedor vale. Vazio = todos, e a tradução é de
                 * `App\Support\ConfiguracaoDoLogin::painelAutorizado()` — o campo só guarda a
                 * lista.
                 *
                 * `Paineis::opcoes()` como `options()`: a chave é o id do painel (`admin`) e o
                 * rótulo é o path (`/admin`). A lista sai de `Filament::getPanels()`, então painel
                 * novo no kit aparece aqui sozinho.
                 *
                 * `->visible($ligado)` como os dois campos de credencial: escolher painéis de um
                 * provedor desligado não decide nada. É UX — a barreira de verdade é a do
                 * `LoginSocialController`.
                 *
                 * Ver `wikis/specs/feat/login-social-por-painel/login-social-por-painel/`.
                 */
                Select::make($provedor->propriedadeDeSettings('paineis'))
                    ->label(__('Panels where this provider applies'))
                    ->helperText(__('Blank = all panels. Choose to restrict — for example, corporate Google only in /admin.'))
                    ->multiple()
                    ->options(Paineis::opcoes())
                    ->visible($ligado),
            ]);
    }

    /** Onde criar o app OAuth de cada provedor — o mesmo roteiro que os READMEs detalham. */
    private function ondeCriarOApp(ProvedorSocial $provedor): string
    {
        return match ($provedor) {
            ProvedorSocial::Google   => __('console.cloud.google.com → APIs & services → Credentials → OAuth client ID.'),
            ProvedorSocial::Github   => __('github.com/settings/developers → OAuth Apps → New OAuth App. The kit asks for the user:email scope, and that is what lets us confirm the e-mail address.'),
            ProvedorSocial::LinkedIn => __('linkedin.com/developers → Create app → Products → Sign In with LinkedIn using OpenID Connect. Without that product the provider does not return an e-mail.'),
            ProvedorSocial::X        => __('developer.x.com → Projects & Apps → User authentication settings, Web App type with OAuth 2.0. Asking for the e-mail requires users.email, and X only delivers it with that scope.'),
        };
    }

    private function abaKit(): Tab
    {
        return Tab::make('Kit')
            ->icon('heroicon-o-squares-2x2')
            ->schema([
                Toggle::make('hub_de_navegacao')
                    ->label(__('Navigation hub in cards'))
                    ->helperText(__('A card grid with the destinations, in the /admin and /app panels. /infra has an independent hub, not this key.')),

                /*
                 * Interruptor lido por REQUEST: os três providers passam uma Closure para
                 * `->unsavedChangesAlerts()`, e o Filament a avalia no render. Salvar aqui vale
                 * no próximo F5, sem cache nem restart.
                 */
                Toggle::make('alerta_alteracoes_nao_salvas')
                    ->label(__('Warn about unsaved changes'))
                    ->helperText(__('When leaving a form with a pending change, the browser asks for confirmation before discarding. Applies to the create and edit screens of all three panels, including the plugins.')),

                /*
                 * Select de NÍVEIS e não Toggle, e a diferença não é estética: apertar por
                 * `--spacing` distorce proporções (ícone e caixa de seleção encolhem junto), a
                 * distorção escala com a intensidade e ela é questão de gosto e de tela. Um
                 * booleano fixaria uma intensidade para todo mundo. Ver ADR-04.
                 *
                 * Lido por REQUEST no render hook `STYLES_BEFORE`, como o alerta acima: salvar
                 * aqui vale no próximo F5, sem `npm run build`, sem cache e sem deploy.
                 *
                 * `DensidadeDoLayout::opcoes()` e NÃO `->options(DensidadeDoLayout::class)`: a
                 * segunda forma faz o Filament devolver instância do enum no estado, e o
                 * `fill()` do spatie a atribui direto à propriedade tipada `string` — `TypeError`
                 * ao salvar a TELA INTEIRA, não só este campo. O docblock de `opcoes()` tem o
                 * caminho completo; a suíte pegou com 60 casos de outras features.
                 *
                 * O enum continua sendo a única cópia do vocabulário: nível novo aparece aqui
                 * sem tocar nesta tela.
                 */
                Select::make('densidade_do_layout')
                    ->label('Densidade do layout')
                    ->helperText(__('Tightens the statistic cards, tables, sidebar menu and buttons of all three panels at once. "Comfortable" is the Filament default and changes nothing.'))
                    ->options(DensidadeDoLayout::opcoes())
                    ->selectablePlaceholder(false)
                    ->required(),

                /*
                 * Desligado por padrão: a versão do kit é métrica interna do starter, e quem
                 * entrega o produto a um cliente final não tem por que anunciar de qual kit ele
                 * nasceu. A versão do SISTEMA fica na aba Identidade.
                 */
                Toggle::make('exibir_versao_do_kit')
                    ->label(__('Also show the kit version in the footer'))
                    ->helperText(__('Adds the starter kit version next to the system version. Off, the footer shows only yours. The kit version remains available in `php artisan kit:info`.')),

                /*
                 * O interruptor do dashboard dinâmico. A troca é por REQUEST — as duas
                 * páginas (dinâmica e clássica) estão sempre registradas e
                 * `App\Support\DashboardDinamico` decide qual atende — então salvar aqui
                 * vale já no próximo F5, sem cache nem restart.
                 *
                 * Desligar NUNCA apaga dado: os dashboards montados ficam em
                 * `dashboards`/`dashboard_widgets` e voltam ao religar.
                 */
                Section::make(__('Dynamic dashboard'))
                    ->description(__('The panel landing screen becomes a grid the user arranges, moves and resizes (mddev31/filament-dynamic-dashboard). When off, the usual classic dashboard renders.'))
                    ->icon('heroicon-o-squares-plus')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('dashboard_dinamico_habilitado')
                            ->label(__('Dynamic dashboard as the landing screen'))
                            ->helperText(__('When on, each panel root answers with the editable grid at /dashboard-dinamico; when off, the root answers the classic one. Only people with the "Manage:Dashboard" permission can arrange it — the others only see.'))
                            ->live(),

                        Select::make('dashboard_dinamico_paineis')
                            ->label(__('Panels where it applies'))
                            ->helperText(__('Blank = all panels. Choose to restrict — for example, only /app.'))
                            ->multiple()
                            ->options(Paineis::opcoes())
                            ->visible(fn (Get $get): bool => (bool) $get('dashboard_dinamico_habilitado')),
                    ]),

                TextInput::make('rotulo_da_organizacao')
                    ->label(__('What to call each organization'))
                    ->helperText(__('Vocabulary of the INSTALLATION (Company, Client, School, Unit). It is not one organization\'s setting — that lives in /admin/organizacoes.'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('rotulo_das_organizacoes')
                    ->label('E no plural')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /**
     * Os formatos de imagem aceitos nos campos de arquivo — todos, menos SVG.
     *
     * São os nove da regra `image` do Laravel
     * (`.../Validation/Concerns/ValidatesAttributes.php:1531-1540`) MAIS `ico` e
     * `tiff`, e as duas adições não são capricho: `.ico` é o formato que a maioria
     * dos kits de marca ainda entrega para favicon, o requisito diz que "o
     * restante pode ser qualquer tipo de image", e **este kit serve um
     * `public/favicon.ico`**. Recusar `.ico` na tela de favicon do kit que embarca
     * um `.ico` é a inconsistência que o quality gate achou.
     *
     * A lista é escrita e não herdada da regra `image` por isso mesmo: `image` é
     * mantida pelo framework, o que é bom, mas ela decide por conta própria que
     * `.ico` não é imagem — e aqui essa decisão é do requisito. O preço é revisitar
     * esta linha quando um formato novo virar comum; o teste de formatos aceitos é
     * onde isso aparece.
     *
     * SVG fica fora, e é o único que fica: ele carrega `<script>` e estes arquivos
     * são servidos pelo mesmo origin da aplicação.
     *
     * ⚠️ `tif` E `tiff`, e o primeiro não é redundância. A regra `mimes` compara
     * `guessExtension()`, que devolve a PRIMEIRA extensão que o Symfony associa ao
     * MIME — e para `image/tiff` a primeira é `tif`, não `tiff`
     * (`MimeTypes::getExtensions('image/tiff')` → `['tif', 'tiff', …]`). Com só
     * `tiff` na lista, um TIFF era recusado com a mensagem dizendo que TIFF é
     * aceito. Quem pegou isso foi o caso de partição por formato.
     */
    private const FORMATOS_DE_IMAGEM = 'jpg,jpeg,png,gif,bmp,webp,avif,heic,heif,ico,tif,tiff';

    /**
     * Campo de imagem no disco `public`, com teto de tamanho e sem SVG.
     *
     * `->disk('public')->visibility('public')` explícito, e não por default: no
     * Filament o default é `private`, e favicon, logo e arte aparecem ANTES de
     * haver sessão — na tela de login. Arquivo privado existe no disco e responde
     * 403 no `<head>` de toda página. Ver `.ai/rules/models.md` e ADR-03 da wiki
     * `settings-do-kit`.
     *
     * ## `->image()` E `->rule('image')`, e os dois são necessários
     *
     * São coisas diferentes com o mesmo nome. O `->image()` do Filament é
     * açúcar para `acceptedFileTypes(['image/*'])`
     * (vendor/filament/forms/src/Components/FileUpload.php:130-134): ele vira o
     * `accept` do seletor de arquivo do sistema e a regra `mimetypes:image/*` —
     * e `image/svg+xml` CASA com esse curinga
     * (.../Validation/Concerns/ValidatesAttributes.php:1781-1783). Era por isso
     * que SVG passava aqui.
     *
     * A barreira é a regra `mimes` do LARAVEL, com a lista de `FORMATOS_DE_IMAGEM`.
     * Ela compara `guessExtension()`, que vem do MIME derivado do CONTEÚDO do
     * arquivo (`.../Validation/Concerns/ValidatesAttributes.php:1746-1761`), então
     * renomear um `.svg` para `.png` não passa.
     *
     * SVG carrega `<script>`, e estes três arquivos são servidos pelo MESMO origin
     * da aplicação, com visibilidade pública — abrir a URL executaria o script com
     * acesso ao cookie de sessão. É a razão de a lista ser fechada.
     *
     * ## O teto vem de `TetoDeUpload`, em KILOBYTES
     *
     * `->maxSize()` monta a regra `max:{$size}` do Laravel
     * (vendor/filament/forms/src/Components/BaseFileUpload.php:413-421), e essa
     * regra divide o tamanho do arquivo por 1024
     * (.../Validation/Concerns/ValidatesAttributes.php:2822). Quem sabe disso é
     * `App\Support\TetoDeUpload`, a dona da conversão — aqui e nos outros dois
     * campos de upload do kit.
     *
     * ## O texto de ajuda vem por argumento, e não encadeado
     *
     * As três chamadas encadeavam `->helperText()` DEPOIS de `arquivo()`, o que
     * sobrescreveria qualquer texto definido aqui. Recebendo o texto como
     * argumento, o teto e o aviso de SVG entram nos três de uma vez e
     * acompanham a config.
     */
    private function arquivo(string $nome, string $rotulo, string $ajuda): FileUpload
    {
        $formatos = mb_strtoupper(str_replace(',', ', ', self::FORMATOS_DE_IMAGEM));

        $maximoEmMb = TetoDeUpload::emMb();

        return FileUpload::make($nome)
            ->label($rotulo)
            ->image()
            ->rule('mimes:'.self::FORMATOS_DE_IMAGEM)
            ->maxSize(TetoDeUpload::emKb())
            ->validationMessages([
                'max'   => __('The file is over :max MB.', ['max' => $maximoEmMb]),
                'mimes' => __('SVG is not accepted. Send :formats.', ['formats' => $formatos]),
            ])
            ->helperText($ajuda.' '.__('Up to :max MB, and SVG is not accepted.', ['max' => $maximoEmMb]))
            ->disk('public')
            ->directory('kit')
            ->visibility('public');
    }
}

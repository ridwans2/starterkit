<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Pages\Auth\TelaBloqueio;
use App\Filament\Pages\Auth\TelaDoisFatores;
use App\Filament\Pages\Auth\TelaLogin;
use App\Filament\Pages\Auth\TelaRecuperarSenha;
use App\Filament\Pages\DashboardClassico;
use App\Filament\Pages\MyProfilePage;
use App\Filament\Spotlight\AcoesDeCriacao;
use App\Filament\Spotlight\PagesAutorizadasCategory;
use App\Filament\Spotlight\ResourcesAutorizadasCategory;
use App\Livewire\DefinirSenhaPorEmail;
use App\Support\AvatarDeIniciais;
use App\Support\CorPrimaria;
use App\Support\DensidadeDoLayout;
use App\Support\IdentidadeDoKit;
use Asmit\ResizedColumn\ResizedColumnPlugin;
use BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Coolsam\Modules\ModulesPlugin;
use DutchCodingCompany\FilamentDeveloperLogins\FilamentDeveloperLoginsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Gsferro\FilamentOdometerEasy\FilamentOdometerEasyPlugin;
use Harvirsidhu\FilamentCards\FilamentCardsPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use lockscreen\FilamentLockscreen\Lockscreen;
use MortalKiller\FilamentPageHeader\PageHeaderPlugin;
use Prodstarter\FilamentNotificationCenter\FilamentNotificationCenterPlugin;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;
use SolutionForest\FilamentSimpleLightBox\SimpleLightBoxPlugin;
use Wallacemartinss\FilamentOnboarding\FilamentOnboardingPlugin;
use Wezlo\FilamentSearchSpotlight\Categories\ActionsCategory;
use Wezlo\FilamentSearchSpotlight\Categories\RecordsCategory;
use Wezlo\FilamentSearchSpotlight\FilamentSearchSpotlightPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->strictAuthorization()
            ->login()
            ->passwordReset()
            ->brandName(fn (): string => config('app.name').' • Admin')

            ->brandLogo(fn (): ?string => IdentidadeDoKit::logo())
            ->brandLogoHeight('2rem')
            ->favicon(fn (): ?string => IdentidadeDoKit::favicon())
            ->colors(fn (): array => CorPrimaria::paleta())

            ->defaultAvatarProvider(AvatarDeIniciais::class)

            ->unsavedChangesAlerts(fn (): bool => (bool) config('kit.alerta_alteracoes_nao_salvas'))
            ->sidebarCollapsibleOnDesktop()

            ->sidebarWidth(fn (): string => DensidadeDoLayout::deConfig()->larguraDaSidebar())
            ->maxContentWidth(Width::Full)
            ->subNavigationPosition(SubNavigationPosition::Top)
            ->databaseNotifications()
            ->databaseNotificationsPolling(config('broadcasting.default') === 'reverb' ? null : '30s')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->pages([
                Dashboard::class,
                DashboardClassico::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->bootUsing(function (Panel $panel): void {
                AcoesDeCriacao::registrar();

                if (config('lockscreen.enabled')) {
                    $panel->userMenuItems([TelaBloqueio::itemDeMenu($panel->getId())]);
                }
            })
            ->plugins([
                FilamentSearchSpotlightPlugin::make()
                    ->keyBinding(['mod+k'])
                    ->disableDefaultGlobalSearch()
                    ->resultLimitPerCategory(5)
                    ->actionsEnabled()
                    ->disableCreateActions()
                    ->placeholder('Buscar registros e telas...')
                    ->categories([
                        RecordsCategory::class,
                        ResourcesAutorizadasCategory::class,
                        PagesAutorizadasCategory::class,
                        ActionsCategory::class,
                    ]),

                AuthDesignerPlugin::make()
                    ->login(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->usingPage(TelaLogin::class)
                        ->media(IdentidadeDoKit::arteDoLogin(), alt: config('app.name'))
                        ->mediaPosition(MediaPosition::Left)
                        ->mediaSize('70%')
                        ->themeToggle()
                    )
                    ->passwordReset(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->usingPage(TelaRecuperarSenha::class)
                        ->media(IdentidadeDoKit::arteDoLogin(), alt: config('app.name'))
                        ->mediaPosition(MediaPosition::Right)
                        ->mediaSize('70%')
                        ->themeToggle()
                    )

                    ->emailVerification(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->media(IdentidadeDoKit::arteDoLogin(), alt: config('app.name'))
                        ->mediaPosition(MediaPosition::Right)
                        ->mediaSize('70%')
                        ->themeToggle()
                    ),

                FilamentShieldPlugin::make()
                    ->modelLabel('Role')
                    ->pluralModelLabel('Roles')
                    ->navigationLabel('Roles')

                    ->navigationGroup('Administration'),

                BreezyCore::make()
                    ->myProfile(shouldRegisterUserMenu: true, hasAvatars: true, slug: 'meu-perfil', userMenuLabel: 'My profile')
                    ->myProfileComponents(['definir_senha_por_email' => DefinirSenhaPorEmail::class])

                    ->customMyProfilePage(MyProfilePage::class)

                    ->enableTwoFactorAuthentication(action: TelaDoisFatores::class),

                Lockscreen::make()
                    ->enablePlugin((bool) config('lockscreen.enabled'))
                    ->enableIdleTimeout((int) config('lockscreen.idle_timeout'))
                    ->enableRateLimit(limit: 5, decayMinutes: 5, forceLogout: true),

                FilamentOnboardingPlugin::make()
                    ->manageFlows((bool) config('filament-onboarding.enabled', true))
                    ->launcher(false)
                    ->tours(false),

                EnvironmentIndicatorPlugin::make()
                    ->visible(fn (): bool => ! app()->isProduction()),

                FilamentOdometerEasyPlugin::make()
                    ->delay(1000)
                    ->duration(1500)
                    // Sem isto o badge de contagem some com a sidebar recolhida.
                    ->badgeOnCollapsedSidebar(),

                ResizedColumnPlugin::make()
                    ->preserveOnSession(),

                FilamentNotificationCenterPlugin::make(),

                SimpleLightBoxPlugin::make(),

                FilamentApexChartsPlugin::make(),

                FilamentCardsPlugin::make(),

                FilamentExceptionsPlugin::make()
                    ->registerNavigation(false),

                PageHeaderPlugin::make(),

                ModulesPlugin::make(),

                FilamentDeveloperLoginsPlugin::make()
                    ->enabled(app()->environment('local'))
                    ->users([
                        'Admin' => 'penyair@gmail.com',
                        'User'  => 'user@example.com',
                    ]),

            ])

            ->emailVerification(null, isRequired: false)

            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.spotlight-trigger')->render(),
            )

            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): string => view('filament.user-menu-header')->render(),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

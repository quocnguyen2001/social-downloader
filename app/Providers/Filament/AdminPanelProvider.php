<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\AuthenticatedUserApiLimitsSettings;
use App\Filament\Pages\CookieSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\GuestUserApiLimitsSettings;
use App\Filament\Pages\PaymentGatewayConfiguration;
use App\Filament\Resources\ApiKeyResource;
use App\Filament\Resources\ApiRequestResource;
use App\Filament\Resources\DownloadSessionResource;
use App\Filament\Resources\MembershipPlanResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\UserResource;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder->items([
                    ...Dashboard::getNavigationItems(),
                ])->groups([
                    NavigationGroup::make(__('filament.navigation.groups.api_management'))
                        ->items([
                            ...ApiKeyResource::getNavigationItems(),
                            ...ApiRequestResource::getNavigationItems(),
                        ]),
                    NavigationGroup::make(__('filament.navigation.groups.user_management'))
                        ->items([
                            ...UserResource::getNavigationItems(),
                            ...MembershipPlanResource::getNavigationItems(),
                        ]),
                    NavigationGroup::make(__('filament.navigation.groups.downloads'))
                        ->items([
                            ...DownloadSessionResource::getNavigationItems(),
                        ]),
                    NavigationGroup::make(__('filament.navigation.groups.billing_revenue'))
                        ->items([
                            ...OrderResource::getNavigationItems(),
                            ...TransactionResource::getNavigationItems(),
                        ]),
                    NavigationGroup::make(__('filament.navigation.groups.settings'))
                        ->items([
                            ...GeneralSettings::getNavigationItems(),
                            ...CookieSettings::getNavigationItems(),
                            ...AuthenticatedUserApiLimitsSettings::getNavigationItems(),
                            ...GuestUserApiLimitsSettings::getNavigationItems(),
                            ...PaymentGatewayConfiguration::getNavigationItems(),
                        ]),
                ]);
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        $this->registerLanguageSwitcherRenderHook();
    }

    private function registerLanguageSwitcherRenderHook(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_END,
            fn (): string => view('filament.components.language-switcher')->render(),
        );
    }
}

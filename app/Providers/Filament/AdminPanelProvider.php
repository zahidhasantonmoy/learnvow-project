<?php

namespace App\Providers\Filament;

use App\Filament\Auth\AdminLogin;
use App\Filament\AvatarProviders\LocalAvatarProvider;
use App\Filament\Widgets\AdminOrdersChartWidget;
use App\Filament\Widgets\AdminStatsWidget;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\EnsureAdminPanelAccess;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->login(AdminLogin::class)
            ->brandName(__('admin.panel.brand'))
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                AdminStatsWidget::class,
                AdminOrdersChartWidget::class,
            ])
            // Spec §9/§15 (minor): admin pages are never indexed — the
            // noindex meta the web author pages already carry, via the
            // panel's supported head render hook.
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => '<meta name="robots" content="noindex, nofollow">',
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
                // Builder C: role-based auth (spec A15) — see middleware.
                AuthenticateAdminPanel::class,
                EnsureAdminPanelAccess::class,
            ]);
    }
}

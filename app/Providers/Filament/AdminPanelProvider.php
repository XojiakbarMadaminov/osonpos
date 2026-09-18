<?php

namespace App\Providers\Filament;

use App\Domain\Authorization\AccessibleAdminStores;
use App\Domain\Platform\PlatformOrganizationAccess;
use App\Enums\AdminNavigationGroup;
use App\Http\Controllers\Admin\RedirectAdminHomeController;
use App\Http\Middleware\InitializeStoreContext;
use App\Http\Middleware\InitializeTenantContext;
use App\Support\TenantContext;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups(AdminNavigationGroup::class)
            ->authenticatedRoutes(function (): void {
                Route::get('/', RedirectAdminHomeController::class)->name('home');
            })
            ->renderHook(
                PanelsRenderHook::CONTENT_BEFORE,
                function () {
                    $user = request()->user();
                    $access = app(PlatformOrganizationAccess::class);

                    if (! $user || ! $access->isActiveFor($user, app(TenantContext::class)->id())) {
                        return '';
                    }

                    return view('filament.admin.components.platform-organization-banner', [
                        'organization' => app(TenantContext::class)->requireCurrent(),
                    ]);
                },
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                function () {
                    $stores = app(AccessibleAdminStores::class)->forUser(request()->user());
                    $currentStoreId = (int) session('current_store_id');

                    if (! $stores->contains('id', $currentStoreId)) {
                        $currentOrganizationId = app(TenantContext::class)->id();
                        $currentStoreId = (int) ($stores->firstWhere('organization_id', $currentOrganizationId)?->getKey()
                            ?? $stores->first()?->getKey());
                    }

                    return view('filament.admin.components.context-switcher', compact('stores', 'currentStoreId'));
                },
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn () => view('filament.admin.components.pos-login-required'),
            )
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(AdminNavigationGroup::StaffAndPermissions)
                    ->navigationSort(2),
            ])
            ->authMiddleware([
                Authenticate::class,
                InitializeTenantContext::class,
                InitializeStoreContext::class,
            ], isPersistent: true);
    }
}

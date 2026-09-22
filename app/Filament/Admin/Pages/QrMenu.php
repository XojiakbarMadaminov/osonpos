<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Domain\Catalog\QrMenuCode;
use App\Domain\Subscription\FeaturePermissionAccess;
use App\Enums\AdminNavigationGroup;
use App\Enums\OrganizationPermission;
use App\Support\StoreContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class QrMenu extends Page
{
    protected string $view = 'filament.admin.pages.qr-menu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Catalog;

    protected static ?string $navigationLabel = 'QR menyu';

    protected static ?string $title = 'QR menyu';

    protected static ?int $navigationSort = 3;

    public bool $enabled = false;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->enabled = app(StoreContext::class)->requireCurrent()->is_qr_menu_enabled;
    }

    public static function canAccess(): bool
    {
        $user = request()->user();
        $store = app(StoreContext::class)->current();

        return $user !== null
            && $store !== null
            && app(FeaturePermissionAccess::class)->allows($user, 'qr_menu', OrganizationPermission::QrMenuManage)
            && app(StoreAccess::class)->allows($user, $store);
    }

    public function menuUrl(): string
    {
        return route('menu.show', ['token' => app(StoreContext::class)->requireCurrent()->menu_token]);
    }

    public function qrDataUri(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(app(QrMenuCode::class)->svg($this->menuUrl()));
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate(['enabled' => ['required', 'boolean']]);

        $store = app(StoreContext::class)->requireCurrent();
        $store->is_qr_menu_enabled = $this->enabled;
        $store->save();

        Notification::make()->success()->title('QR menyu sozlamasi saqlandi')->send();
    }

    public function downloadQrCode(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $svg = app(QrMenuCode::class)->svg($this->menuUrl());

        return response()->streamDownload(
            static fn () => print ($svg),
            'filial-qr-menyu.svg',
            ['Content-Type' => 'image/svg+xml'],
        );
    }
}

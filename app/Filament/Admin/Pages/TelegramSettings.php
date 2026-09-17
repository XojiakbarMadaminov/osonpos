<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Subscription\FeaturePermissionAccess;
use App\Enums\AdminNavigationGroup;
use App\Enums\OrganizationPermission;
use App\Models\TelegramSetting;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TelegramSettings extends Page
{
    protected string $view = 'filament.admin.pages.telegram-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::BranchManagement;

    protected static ?string $navigationLabel = 'Telegram sozlamasi';

    protected static ?string $title = 'Telegram sozlamasi';

    protected static ?int $navigationSort = 6;

    public string $groupChatId = '';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->groupChatId = TelegramSetting::query()
            ->where('organization_id', app(TenantContext::class)->id())
            ->value('group_chat_id') ?? '';
    }

    public static function canAccess(): bool
    {
        $user = request()->user();

        return $user !== null
            && app(FeaturePermissionAccess::class)->allows(
                $user,
                'telegram_payment_notifications',
                OrganizationPermission::TelegramSettingsManage,
            );
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $validated = $this->validate([
            'groupChatId' => ['required', 'regex:/^-?[0-9]+$/', 'max:255'],
        ], [
            'groupChatId.required' => 'Telegram guruh ID sini kiriting.',
            'groupChatId.regex' => 'Telegram guruh ID si faqat raqamlardan iborat bo‘lishi kerak.',
            'groupChatId.max' => 'Telegram guruh ID si 255 ta belgidan oshmasligi kerak.',
        ]);

        $organization = app(TenantContext::class)->requireCurrent();
        $settings = TelegramSetting::query()
            ->where('organization_id', $organization->getKey())
            ->firstOrNew();
        $settings->organization()->associate($organization);
        $settings->group_chat_id = $validated['groupChatId'];
        $settings->save();

        Notification::make()->success()->title('Telegram sozlamasi saqlandi')->send();
    }
}

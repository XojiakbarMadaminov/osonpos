<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintType;
use App\Enums\ShiftStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Admin\Resources\Orders\OrderResource;
use Tests\TestCase;

uses(TestCase::class);

test('the application and common user-facing text use Uzbek', function () {
    expect(config('app.locale'))->toBe('uz')
        ->and(__('auth.failed'))->toBe('Kiritilgan ma’lumotlar tizimdagi yozuvlarga mos kelmadi.')
        ->and(__('Name'))->toBe('Nomi')
        ->and(OrderResource::getModelLabel())->toBe('buyurtma')
        ->and(OrderResource::getPluralModelLabel())->toBe('buyurtmalar');
});

test('user-facing enum and access labels use Uzbek', function () {
    expect(OrderStatus::Open->getLabel())->toBe('Ochiq')
        ->and(OrderType::Takeaway->getLabel())->toBe('Olib ketish')
        ->and(PaymentStatus::Paid->getLabel())->toBe('To‘langan')
        ->and(PaymentMethod::Cash->getLabel())->toBe('Naqd')
        ->and(PrintType::KitchenTicket->getLabel())->toBe('Oshxona cheki')
        ->and(ShiftStatus::Closed->getLabel())->toBe('Yopilgan')
        ->and(SubscriptionStatus::Expired->getLabel())->toBe('Muddati tugagan')
        ->and(OrganizationRole::Cashier->label())->toBe('Kassir')
        ->and(OrganizationPermission::OrdersCreate->label())->toBe('Buyurtma yaratish');
});

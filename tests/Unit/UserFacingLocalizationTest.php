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
        ->and(__('validation.uploaded', ['attribute' => __('validation.attributes.image_path')]))
        ->toBe('mahsulot rasmi faylini yuklab bo‘lmadi. Fayl hajmini tekshirib, qayta urinib ko‘ring.')
        ->and(OrderResource::getModelLabel())->toBe('buyurtma')
        ->and(OrderResource::getPluralModelLabel())->toBe('buyurtmalar');
});

test('user-facing enum and access labels use Uzbek', function () {
    expect(OrderStatus::Open->getLabel())->toBe('Ochiq')
        ->and(OrderType::Takeaway->getLabel())->toBe('Olib ketish')
        ->and(PaymentStatus::Paid->getLabel())->toBe('To‘langan')
        ->and(OrderStatus::Open->getColor())->toBe('warning')
        ->and(OrderStatus::Completed->getColor())->toBe('success')
        ->and(OrderStatus::Cancelled->getColor())->toBe('danger')
        ->and(PaymentStatus::Unpaid->getColor())->toBe('danger')
        ->and(PaymentStatus::PartiallyPaid->getColor())->toBe('warning')
        ->and(PaymentStatus::Paid->getColor())->toBe('success')
        ->and(PaymentStatus::Refunded->getColor())->toBe('gray')
        ->and(OrderType::DineIn->getColor())->toBe('success')
        ->and(OrderType::Takeaway->getColor())->toBe('warning')
        ->and(OrderType::Delivery->getColor())->toBe('info')
        ->and(PaymentMethod::Cash->getLabel())->toBe('Naqd')
        ->and(PrintType::KitchenTicket->getLabel())->toBe('Oshxona cheki')
        ->and(ShiftStatus::Closed->getLabel())->toBe('Yopilgan')
        ->and(SubscriptionStatus::Expired->getLabel())->toBe('Muddati tugagan')
        ->and(OrganizationRole::Cashier->label())->toBe('Kassir')
        ->and(OrganizationPermission::OrdersCreate->label())->toBe('Buyurtma yaratish');
});

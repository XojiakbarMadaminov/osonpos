<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Actions\Printing\CustomerReceiptData;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;

function discountContext(int $subtotal = 100000, int $deliveryFee = 0): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $user,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Cashier->value),
    );
    Subscription::factory()->for($organization)->create();
    $device = Device::factory()->for($organization)->for($store)->create();
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($user)->create();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'total' => $subtotal + $deliveryFee,
    ]);
    OrderItem::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'product_name' => 'Sinov mahsuloti',
        'quantity' => 1,
        'unit_price' => $subtotal,
        'total' => $subtotal,
    ]);

    return [$organization, $store, $user, $device, $shift, $order, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

it('applies percentage or fixed discounts to products but not the delivery fee', function () {
    [, , $user, , , $order, $session] = discountContext(deliveryFee: 10000);

    $this->actingAs($user)->withSession($session)
        ->putJson("/api/pos/orders/{$order->id}/discount", [
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('data.subtotal', 100000)
        ->assertJsonPath('data.delivery_fee', 10000)
        ->assertJsonPath('data.discount_type', DiscountType::Percentage->value)
        ->assertJsonPath('data.discount_value', 10)
        ->assertJsonPath('data.discount_amount', 10000)
        ->assertJsonPath('data.total', 100000)
        ->assertJsonPath('data.balance_due', 100000);

    $this->putJson("/api/pos/orders/{$order->id}/discount", [
        'discount_type' => DiscountType::Fixed->value,
        'discount_value' => 15000,
    ])->assertOk()
        ->assertJsonPath('data.discount_amount', 15000)
        ->assertJsonPath('data.total', 95000);

    $this->deleteJson("/api/pos/orders/{$order->id}/discount")
        ->assertOk()
        ->assertJsonPath('data.discount_type', null)
        ->assertJsonPath('data.discount_value', null)
        ->assertJsonPath('data.discount_amount', 0)
        ->assertJsonPath('data.total', 110000);
});

it('validates discount limits and never reduces the total below existing payments', function () {
    [$organization, $store, $user, , , $order, $session] = discountContext(deliveryFee: 10000);

    foreach ([
        [DiscountType::Percentage, 101, 'Foiz chegirmasi 1 dan 100 gacha bo‘lishi kerak.'],
        [DiscountType::Fixed, 100001, 'Chegirma summasi mahsulotlar oralig‘idan oshmasligi kerak.'],
    ] as [$type, $value, $message]) {
        $this->actingAs($user)->withSession($session)
            ->putJson("/api/pos/orders/{$order->id}/discount", [
                'discount_type' => $type->value,
                'discount_value' => $value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount_value.0', $message);
    }

    Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'amount' => 95000,
    ]);

    $this->putJson("/api/pos/orders/{$order->id}/discount", [
        'discount_type' => DiscountType::Percentage->value,
        'discount_value' => 20,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.discount_value.0', 'Chegirmadan keyingi jami summa avval to‘langan summadan kam bo‘lishi mumkin emas.');

    expect($order->refresh()->discount_type)->toBeNull()
        ->and($order->discount_amount)->toBe(0)
        ->and($order->total)->toBe(110000);
});

it('requires a current shift and an open current-store order for discount changes', function () {
    [, , $user, , $shift, $order, $session] = discountContext();
    $payload = [
        'discount_type' => DiscountType::Fixed->value,
        'discount_value' => 10000,
    ];
    $shift->delete();

    $this->actingAs($user)->withSession($session)
        ->putJson("/api/pos/orders/{$order->id}/discount", $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.shift.0', 'Chegirma qo‘llash uchun avval smenani oching.');

    Shift::factory()->for($order->organization)->for($order->store)->for($order->device)->for($user)->create();
    $order->forceFill(['status' => OrderStatus::Completed])->save();

    $this->putJson("/api/pos/orders/{$order->id}/discount", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('order');
});

it('blocks cross-store and unauthorized discount changes', function () {
    [$organization, $store, $user, $device, , , $session] = discountContext();
    $otherStore = Store::factory()->for($organization)->create();
    $otherOrder = Order::factory()->for($organization)->for($otherStore)->for($user, 'creator')->create([
        'subtotal' => 50000,
        'total' => 50000,
    ]);
    $payload = [
        'discount_type' => DiscountType::Fixed->value,
        'discount_value' => 5000,
    ];

    $this->actingAs($user)->withSession($session)
        ->putJson("/api/pos/orders/{$otherOrder->id}/discount", $payload)
        ->assertForbidden();

    $unauthorized = User::factory()->create();
    $organization->users()->attach($unauthorized);
    $store->users()->attach($unauthorized);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    $this->actingAs($unauthorized)->withSession($session)
        ->putJson("/api/pos/orders/{$order->id}/discount", $payload)
        ->assertForbidden();

    expect($otherOrder->refresh()->discount_amount)->toBe(0)
        ->and($order->refresh()->discount_amount)->toBe(0);
});

it('allows a fully discounted product order to be completed without a payment', function () {
    [, , $user, , , $order, $session] = discountContext();

    $this->actingAs($user)->withSession($session)
        ->putJson("/api/pos/orders/{$order->id}/discount", [
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value);

    $this->postJson("/api/pos/orders/{$order->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Completed->value);
});

it('prints discount details on the customer receipt', function () {
    [$organization, $store, $user, , , $order] = discountContext();
    $order->forceFill([
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 10,
        'discount_amount' => 10000,
        'total' => 90000,
        'payment_status' => PaymentStatus::Paid,
    ])->save();
    Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create(['amount' => 90000]);
    $printer = Printer::factory()->for($organization)->for($store)->create(['paper_width' => 80]);

    $receipt = new CustomerReceiptData(
        $order->load(['store', 'table', 'creator', 'items.removals', 'payments', 'deliveryDetail']),
        $printer,
        false,
    );
    $lines = collect($receipt->toArray()['lines']);

    expect($lines->contains(fn (string $line): bool => str_contains($line, 'CHEGIRMA (10%)') && str_ends_with($line, '-10 000')))->toBeTrue()
        ->and($lines->contains(fn (string $line): bool => str_contains($line, 'JAMI UZS') && str_ends_with($line, '90 000')))->toBeTrue();
});

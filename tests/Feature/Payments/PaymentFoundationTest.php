<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

function paymentContext(int $total = 100000): array
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
    Shift::factory()->for($organization)->for($store)->for($device)->for($user)->create();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'subtotal' => $total,
        'total' => $total,
    ]);

    return [$organization, $store, $user, $order, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

it('keeps an order unpaid without payment records', function () {
    [, , , $order] = paymentContext();

    expect($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->payments()->sum('amount'))->toBe(0);
});

it('calculates partial and full payment status transactionally', function () {
    [, , $user, $order, $session] = paymentContext();

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Cash->value,
        'amount' => 40000,
    ])->assertCreated()
        ->assertJsonPath('data.paid_amount', 40000)
        ->assertJsonPath('data.remaining_amount', 60000)
        ->assertJsonPath('data.payment_status', PaymentStatus::PartiallyPaid->value);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Card->value,
        'amount' => 60000,
    ])->assertCreated()
        ->assertJsonPath('data.paid_amount', 100000)
        ->assertJsonPath('data.remaining_amount', 0)
        ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value);

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payments()->pluck('method')->all())->toBe([PaymentMethod::Cash, PaymentMethod::Card]);
});

it('supports mixed payment records using integer UZS amounts', function () {
    [, , $user, $order, $session] = paymentContext(90000);
    $shift = Shift::query()->sole();

    foreach ([
        [PaymentMethod::Cash, 30000],
        [PaymentMethod::Click, 30000],
        [PaymentMethod::Payme, 30000],
    ] as [$method, $amount]) {
        $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
            'method' => $method->value,
            'amount' => $amount,
        ])->assertCreated();
    }

    expect(Payment::query()->count())->toBe(3)
        ->and(Payment::query()->get()->every(fn (Payment $payment) => is_int($payment->amount)))->toBeTrue()
        ->and(Payment::query()->get()->every(fn (Payment $payment) => $payment->shift_id === $shift->id))->toBeTrue()
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('creates cash and card mixed payments atomically and idempotently', function () {
    [, , $user, $order, $session] = paymentContext(80000);
    $cashPaymentId = (string) Str::ulid();
    $cardPaymentId = (string) Str::ulid();
    $payload = [
        'cash_payment_id' => $cashPaymentId,
        'cash_amount' => 30000,
        'card_payment_id' => $cardPaymentId,
        'card_amount' => 50000,
    ];

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/mixed-payments", $payload)
        ->assertCreated()
        ->assertJsonPath('data.cash_payment_id', $cashPaymentId)
        ->assertJsonPath('data.card_payment_id', $cardPaymentId);
    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/mixed-payments", $payload)
        ->assertOk();

    expect(Payment::query()->count())->toBe(2)
        ->and(Payment::query()->where('method', PaymentMethod::Cash)->value('amount'))->toBe(30000)
        ->and(Payment::query()->where('method', PaymentMethod::Card)->value('amount'))->toBe(50000)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('rolls back both mixed payment parts when their sum is invalid', function () {
    [, , $user, $order, $session] = paymentContext(80000);

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/mixed-payments", [
            'cash_payment_id' => (string) Str::ulid(),
            'cash_amount' => 20000,
            'card_payment_id' => (string) Str::ulid(),
            'card_amount' => 50000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment');

    expect(Payment::query()->count())->toBe(0)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

it('prevents a duplicate payment replay from double charging totals', function () {
    [, , $user, $order, $session] = paymentContext();
    $id = (string) Str::ulid();

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'id' => $id,
        'method' => PaymentMethod::Cash->value,
        'amount' => 50000,
    ])->assertCreated();
    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'id' => $id,
        'method' => PaymentMethod::Card->value,
        'amount' => 90000,
    ])->assertOk()->assertJsonPath('data.paid_amount', 50000);

    expect(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->sole()->amount)->toBe(50000)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);
});

it('blocks payment creation against orders outside the current tenant and store', function () {
    [, , $user, , $session] = paymentContext();
    $foreignOrder = Order::factory()->create(['total' => 10000]);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$foreignOrder->id}/payments", [
        'method' => PaymentMethod::Other->value,
        'amount' => 10000,
    ])->assertForbidden();

    expect(Payment::query()->count())->toBe(0);
});

it('does not authorize physical payment deletion', function () {
    [$organization, $store, $user, $order] = paymentContext();
    $payment = Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create();
    app(TenantContext::class)->resolveFor($user, $organization->id);

    expect($user->can('delete', $payment))->toBeFalse();
});

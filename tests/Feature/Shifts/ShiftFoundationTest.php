<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;

function shiftContext(OrganizationRole $role = OrganizationRole::Cashier): array
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
        fn (User $tenantUser) => $tenantUser->assignRole($role->value),
    );
    Subscription::factory()->for($organization)->create();
    $device = Device::factory()->for($organization)->for($store)->create();

    return [$organization, $store, $user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        'current_device_id' => $device->id,
    ]];
}

it('opens and exposes the current cashier shift', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();

    $response = $this->actingAs($cashier)->withSession($session)->postJson('/api/pos/shifts', [
        'opening_cash' => 120000,
    ])->assertCreated()
        ->assertJsonPath('data.status', ShiftStatus::Open->value)
        ->assertJsonPath('data.opening_cash', 120000);

    $this->actingAs($cashier)->withSession($session)->getJson('/api/pos/shifts/current')
        ->assertOk()
        ->assertJsonPath('data.id', $response->json('data.id'))
        ->assertJsonPath('data.cash_payments_total', 0)
        ->assertJsonPath('data.expected_cash', 120000)
        ->assertJsonPath('data.cash_difference', null);
    $shift = Shift::query()->sole();

    expect($shift->organization_id)->toBe($organization->id)
        ->and($shift->store_id)->toBe($store->id)
        ->and($shift->device_id)->toBe($device->id)
        ->and($shift->user_id)->toBe($cashier->id);
});

it('prevents duplicate open shifts for a user or device', function () {
    [, , $cashier, , $session] = shiftContext();

    $this->actingAs($cashier)->withSession($session)->postJson('/api/pos/shifts', ['opening_cash' => 0])->assertCreated();
    $this->actingAs($cashier)->withSession($session)->postJson('/api/pos/shifts', ['opening_cash' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('shift');

    expect(Shift::query()->count())->toBe(1);
});

it('closes only the current user and device shift', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create();

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/shifts/{$shift->id}/close", ['closing_cash' => 175000])
        ->assertOk()
        ->assertJsonPath('data.status', ShiftStatus::Closed->value)
        ->assertJsonPath('data.closing_cash', 175000);

    expect($shift->refresh()->closed_at)->not->toBeNull();
});

it('rejects shift closure from another device', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $otherDevice = Device::factory()->for($organization)->for($store)->create();
    $shift = Shift::factory()->for($organization)->for($store)->for($otherDevice)->for($cashier)->create();

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/shifts/{$shift->id}/close", ['closing_cash' => 0])
        ->assertUnprocessable();

    expect($shift->refresh()->status)->toBe(ShiftStatus::Open)
        ->and($device->is($otherDevice))->toBeFalse();
});

it('denies shift management without permission', function () {
    [, , $waiter, , $session] = shiftContext(OrganizationRole::Waiter);

    $this->actingAs($waiter)->withSession($session)
        ->postJson('/api/pos/shifts', ['opening_cash' => 0])
        ->assertForbidden();
});

it('requires an active current shift for cash but not card payments', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create([
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Cash->value,
        'amount' => 10000,
    ])->assertUnprocessable()->assertJsonValidationErrors('shift');
    $cardPayment = $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Card->value,
        'amount' => 10000,
    ])->assertCreated();

    expect(Payment::query()->findOrFail($cardPayment->json('data.id'))->shift_id)->toBeNull();

    $shiftResponse = $this->actingAs($cashier)->withSession($session)
        ->postJson('/api/pos/shifts', ['opening_cash' => 50000])
        ->assertCreated();
    $cashPayment = $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Cash->value,
        'amount' => 15000,
    ])->assertCreated();

    expect(Payment::query()->findOrFail($cashPayment->json('data.id'))->shift_id)
        ->toBe($shiftResponse->json('data.id'));

    $this->actingAs($cashier)->withSession($session)->getJson('/api/pos/shifts/current')
        ->assertOk()
        ->assertJsonPath('data.payment_totals.CARD', 0)
        ->assertJsonPath('data.payment_totals.CASH', 15000)
        ->assertJsonPath('data.cash_payments_total', 15000)
        ->assertJsonPath('data.expected_cash', 65000);
});

it('blocks closing a shift while its device still has open orders', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create([
        'opened_at' => now()->subHour(),
    ]);
    Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create([
        'opened_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/shifts/{$shift->id}/close", ['closing_cash' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shift');

    expect($shift->refresh()->status)->toBe(ShiftStatus::Open);
});

it('calculates expected cash and the closing difference', function () {
    [$organization, $store, $cashier, $device] = shiftContext();
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create([
        'opening_cash' => 100000,
        'closing_cash' => 145000,
        'status' => ShiftStatus::Closed,
        'closed_at' => now(),
    ]);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create();
    Payment::factory()->for($organization)->for($store)->for($device)->for($shift)->for($order)->for($cashier, 'creator')->create([
        'method' => PaymentMethod::Cash,
        'amount' => 50000,
    ]);

    expect($shift->expectedCash())->toBe(150000)
        ->and($shift->cashDifference())->toBe(-5000);
});

it('shows tenant-safe read-only shift history in the admin panel', function () {
    [$organization, $store, $manager, $device, $session] = shiftContext(OrganizationRole::Manager);
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($manager)->create();
    $foreignShift = Shift::factory()->create();
    $inaccessibleStore = Store::factory()->for($organization)->create();
    $inaccessibleDevice = Device::factory()->for($organization)->for($inaccessibleStore)->create();
    $inaccessibleCashier = User::factory()->create();
    $organization->users()->attach($inaccessibleCashier);
    $inaccessibleShift = Shift::factory()
        ->for($organization)
        ->for($inaccessibleStore)
        ->for($inaccessibleDevice)
        ->for($inaccessibleCashier)
        ->create();

    $this->actingAs($manager)->withSession($session)
        ->get('/admin/shifts')
        ->assertOk()
        ->assertSee($manager->name);
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/shifts/{$shift->id}")
        ->assertOk();
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/shifts/{$foreignShift->id}")
        ->assertNotFound();
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/shifts/{$inaccessibleShift->id}")
        ->assertNotFound();
});

it('limits cashier shift history to the current cashier', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $ownShift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create([
        'status' => ShiftStatus::Closed,
        'closed_at' => now(),
    ]);
    $otherCashier = User::factory()->create();
    $organization->users()->attach($otherCashier);
    $store->users()->attach($otherCashier);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $otherCashier,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Cashier->value),
    );
    $otherDevice = Device::factory()->for($organization)->for($store)->create();
    $otherShift = Shift::factory()->for($organization)->for($store)->for($otherDevice)->for($otherCashier)->create();

    $this->actingAs($cashier)->withSession($session)
        ->get("/admin/shifts/{$ownShift->id}")
        ->assertOk();
    $this->actingAs($cashier)->withSession($session)
        ->get("/admin/shifts/{$otherShift->id}")
        ->assertNotFound();
});

<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
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
        ->assertOk()->assertJsonPath('data.id', $response->json('data.id'));
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
    $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Card->value,
        'amount' => 10000,
    ])->assertCreated();
});

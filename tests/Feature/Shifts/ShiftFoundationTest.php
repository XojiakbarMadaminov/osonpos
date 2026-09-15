<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Actions\Shifts\CloseShift;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrderStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Filament\Admin\Resources\Shifts\Pages\ListShifts;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($user, $organization->id);
    app(StoreContext::class)->resolveFor($user, $tenantContext, $store->id);

    return [$organization, $store, $user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
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

it('requires an active current shift for every payment method', function (PaymentMethod $method) {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create([
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => $method->value,
        'amount' => 10000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('shift')
        ->assertJsonPath('errors.shift.0', 'To‘lovni qabul qilish uchun avval smenani oching.');

    $shiftResponse = $this->actingAs($cashier)->withSession($session)
        ->postJson('/api/pos/shifts', ['opening_cash' => 50000])
        ->assertCreated();
    $payment = $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => $method->value,
        'amount' => 15000,
    ])->assertCreated();

    expect(Payment::query()->findOrFail($payment->json('data.id'))->shift_id)
        ->toBe($shiftResponse->json('data.id'));
})->with(PaymentMethod::cases());

it('does not use a shift opened on another device for payment', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $otherDevice = Device::factory()->for($organization)->for($store)->create();
    Shift::factory()->for($organization)->for($store)->for($otherDevice)->for($cashier)->create();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create();

    $this->actingAs($cashier)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => PaymentMethod::Card->value,
        'amount' => 10000,
    ])->assertUnprocessable()->assertJsonValidationErrors('shift');

    expect(Payment::query()->count())->toBe(0);
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

it('lets a manager close an abandoned open shift from admin context', function () {
    [$organization, $store, $manager, $device] = shiftContext(OrganizationRole::Manager);
    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $store->users()->attach($cashier);
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create([
        'opening_cash' => 100000,
    ]);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create([
        'status' => OrderStatus::Completed,
    ]);
    Payment::factory()->for($organization)->for($store)->for($device)->for($shift)->for($order)->for($cashier, 'creator')->create([
        'method' => PaymentMethod::Cash,
        'amount' => 25000,
    ]);
    app(TenantContext::class)->resolveFor($manager, $organization->id);

    app(CloseShift::class)->executeAsSupervisor($shift, $manager, 123000);

    expect($shift->refresh()->status)->toBe(ShiftStatus::Closed)
        ->and($shift->closing_cash)->toBe(123000)
        ->and($shift->closed_at)->not->toBeNull()
        ->and($shift->expectedCash())->toBe(125000)
        ->and($shift->cashDifference())->toBe(-2000);
});

it('closes an abandoned shift through the admin table action', function () {
    [$organization, $store, $manager, $device, $session] = shiftContext(OrganizationRole::Manager);
    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $store->users()->attach($cashier);
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create();

    $this->actingAs($manager)->withSession($session);
    app(TenantContext::class)->resolveFor($manager, $organization->id);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListShifts::class)
        ->assertTableActionVisible('close_shift', $shift)
        ->callTableAction('close_shift', $shift, ['closing_cash' => 42342])
        ->assertHasNoActionErrors()
        ->assertTableActionNotMounted('close_shift');

    expect($shift->refresh()->status)->toBe(ShiftStatus::Closed)
        ->and($shift->closing_cash)->toBe(42342);
});

it('does not let a cashier use the supervisor shift close action', function () {
    [$organization, $store, $cashier, $device] = shiftContext();
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create();
    app(TenantContext::class)->resolveFor($cashier, $organization->id);

    expect(fn () => app(CloseShift::class)->executeAsSupervisor($shift, $cashier, 0))
        ->toThrow(AuthorizationException::class);

    expect($shift->refresh()->status)->toBe(ShiftStatus::Open);
});

it('keeps the open-order guard when a supervisor closes a shift', function () {
    [$organization, $store, $manager, $device] = shiftContext(OrganizationRole::Manager);
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($manager)->create();
    Order::factory()->for($organization)->for($store)->for($device)->for($manager, 'creator')->create();
    app(TenantContext::class)->resolveFor($manager, $organization->id);

    expect(fn () => app(CloseShift::class)->executeAsSupervisor($shift, $manager, 0))
        ->toThrow(ValidationException::class, 'Smenani yopishdan oldin bu qurilmadagi ochiq buyurtmalarni yoping yoki bekor qiling.');

    expect($shift->refresh()->status)->toBe(ShiftStatus::Open);
});

it('shows an Uzbek admin notification when open orders block shift closure', function () {
    [$organization, $store, $manager, $device, $session] = shiftContext(OrganizationRole::Manager);
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($manager)->create();
    Order::factory()->for($organization)->for($store)->for($device)->for($manager, 'creator')->create();

    $this->actingAs($manager)->withSession($session);
    app(TenantContext::class)->resolveFor($manager, $organization->id);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListShifts::class)
        ->callTableAction('close_shift', $shift, ['closing_cash' => 42342])
        ->assertHasNoActionErrors()
        ->assertTableActionNotMounted('close_shift')
        ->assertNotified('Smena yopilmadi');

    expect($shift->refresh()->status)->toBe(ShiftStatus::Open);
});

it('blocks supervisor closure across tenant and inaccessible store boundaries', function (string $boundary) {
    [$organization, $store, $manager] = shiftContext(OrganizationRole::Manager);
    $shiftOrganization = $boundary === 'tenant' ? Organization::factory()->create() : $organization;
    $shiftStore = Store::factory()->for($shiftOrganization)->create();
    $shiftDevice = Device::factory()->for($shiftOrganization)->for($shiftStore)->create();
    $shiftCashier = User::factory()->create();
    $shiftOrganization->users()->attach($shiftCashier);
    $shiftStore->users()->attach($shiftCashier);
    $shift = Shift::factory()->for($shiftOrganization)->for($shiftStore)->for($shiftDevice)->for($shiftCashier)->create();
    app(TenantContext::class)->resolveFor($manager, $organization->id);

    expect(fn () => app(CloseShift::class)->executeAsSupervisor($shift, $manager, 0))
        ->toThrow(AuthorizationException::class);

    expect($shift->refresh()->status)->toBe(ShiftStatus::Open)
        ->and($store->is($shiftStore))->toBeFalse();
})->with(['tenant', 'store']);

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
        ->assertOk()
        ->assertSee('Smenani yopish');
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/shifts/{$foreignShift->id}")
        ->assertNotFound();
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/shifts/{$inaccessibleShift->id}")
        ->assertNotFound();
});

it('limits cashier shift history to the current cashier', function () {
    [$organization, $store, $cashier, $device, $session] = shiftContext();
    $ownShift = Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create();
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
        ->assertOk()
        ->assertDontSee('Smenani yopish');
    $this->actingAs($cashier)->withSession($session)
        ->get("/admin/shifts/{$otherShift->id}")
        ->assertNotFound();
});

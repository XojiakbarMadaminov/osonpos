<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrderType;
use App\Enums\OrganizationRole;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Printer;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;

function securityContext(OrganizationRole $role = OrganizationRole::Manager, bool $activeSubscription = true): array
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
    $plan = Plan::factory()->create();
    Subscription::factory()->for($organization)->for($plan)->create([
        'ends_at' => $activeSubscription ? now()->addMonth() : now()->subDay(),
    ]);
    $device = Device::factory()->for($organization)->for($store)->create();

    return [$organization, $store, $user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        'current_device_id' => $device->id,
    ]];
}

it('blocks order IDOR across organizations and stores', function () {
    [$organization, $store, $user, , $session] = securityContext();
    $otherStore = Store::factory()->for($organization)->create();
    $otherStoreOrder = Order::factory()->for($organization)->for($otherStore)->for($user, 'creator')->create();
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();
    $foreignOrder = Order::factory()->for($foreignOrganization)->for($foreignStore)->for($user, 'creator')->create();

    $this->actingAs($user)->withSession($session)->getJson("/api/pos/orders/{$otherStoreOrder->id}")->assertForbidden();
    $this->actingAs($user)->withSession($session)->getJson("/api/pos/orders/{$foreignOrder->id}")->assertForbidden();
    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$foreignOrder->id}/payments", [
        'method' => 'CASH',
        'amount' => 1000,
    ])->assertForbidden();
});

it('does not trust client provided organization or store identifiers', function () {
    [$organization, $store, $user, , $session] = securityContext();
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();

    $response = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
        'organization_id' => $foreignOrganization->id,
        'store_id' => $foreignStore->id,
    ])->assertCreated();

    $order = Order::query()->findOrFail($response->json('data.id'));
    expect($order->organization_id)->toBe($organization->id)
        ->and($order->store_id)->toBe($store->id);
});

it('blocks direct payment requests without payment permission', function () {
    [$organization, $store, $waiter, , $session] = securityContext(OrganizationRole::Waiter);
    $order = Order::factory()->for($organization)->for($store)->for($waiter, 'creator')->create(['total' => 10000]);

    $this->actingAs($waiter)->withSession($session)->postJson("/api/pos/orders/{$order->id}/payments", [
        'method' => 'CASH',
        'amount' => 10000,
    ])->assertForbidden();
});

it('blocks transaction requests after subscription expiry', function () {
    [, , $user, , $session] = securityContext(activeSubscription: false);

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
    ])->assertForbidden();
});

it('rejects a device from another tenant even when its id is in session', function () {
    [, , $user, , $session] = securityContext();
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();
    $foreignDevice = Device::factory()->for($foreignOrganization)->for($foreignStore)->create();
    $session['current_device_id'] = $foreignDevice->id;

    $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertForbidden();
});

it('rejects binding a printer from another store', function () {
    [, , $user, , $session] = securityContext();
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();
    $foreignPrinter = Printer::factory()->for($foreignOrganization)->for($foreignStore)->create();

    $this->actingAs($user)->withSession($session)->putJson("/api/pos/printers/{$foreignPrinter->id}/binding", [
        'system_name' => 'Injected Printer',
    ])->assertUnprocessable();
});

it('rate limits signing requests independently from normal POS requests', function () {
    [, , $user, , $session] = securityContext();

    foreach (range(1, 30) as $attempt) {
        $this->actingAs($user)->withSession($session)->postJson('/api/pos/qz/sign', ['data' => 'payload'])->assertNotFound();
    }

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/qz/sign', ['data' => 'payload'])->assertTooManyRequests();
});

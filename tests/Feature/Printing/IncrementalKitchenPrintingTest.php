<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Enums\PrintType;
use App\Models\Category;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;

function kitchenContext(OrganizationRole $role = OrganizationRole::Cashier): array
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
    $printer = Printer::factory()->for($organization)->for($store)->for($device)->create([
        'system_name' => 'Kitchen Thermal',
    ]);
    PrintRoute::factory()->for($organization)->for($store)->for($printer)->create([
        'print_type' => PrintType::KitchenTicket,
    ]);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create();

    return [$organization, $store, $user, $device, $order, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        'current_device_id' => $device->id,
    ]];
}

function kitchenItem(Organization $organization, Store $store, Order $order, User $user, string $name): OrderItem
{
    $category = Category::factory()->for($organization)->create();
    $product = Product::factory()->for($organization)->for($category)->create(['name' => $name]);

    return OrderItem::factory()
        ->for($organization)
        ->for($store)
        ->for($order)
        ->for($product)
        ->for($user, 'creator')
        ->create(['product_name' => $name, 'quantity' => 1]);
}

it('prepares the initial kitchen ticket and marks only confirmed items', function () {
    [$organization, $store, $user, , $order, $session] = kitchenContext();
    $lavash = kitchenItem($organization, $store, $order, $user, 'Lavash');

    $this->actingAs($user)->withSession($session)
        ->getJson('/api/pos/orders')
        ->assertOk()
        ->assertJsonPath('data.0.unprinted_items_count', 1);

    $ticket = $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen")
        ->assertOk()
        ->assertJsonPath('data.is_reprint', false);

    expect($ticket->json('data.lines'))->toContain('1 x LAVASH');

    expect($lavash->refresh()->kitchen_printed_at)->toBeNull();

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen/confirm", [
            'item_ids' => $ticket->json('data.item_ids'),
        ])
        ->assertOk()
        ->assertJsonPath('data.marked_printed', 1);

    expect($lavash->refresh()->kitchen_printed_at)->not->toBeNull();

    $this->actingAs($user)->withSession($session)
        ->getJson('/api/pos/orders')
        ->assertOk()
        ->assertJsonPath('data.0.unprinted_items_count', 0);
});

it('includes only newly added items on the next kitchen ticket', function () {
    [$organization, $store, $user, , $order, $session] = kitchenContext();
    kitchenItem($organization, $store, $order, $user, 'Lavash')
        ->forceFill(['kitchen_printed_at' => now()])
        ->save();
    $cola = kitchenItem($organization, $store, $order, $user, 'Cola');

    $response = $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen")
        ->assertOk();

    expect($response->json('data.item_ids'))->toBe([$cola->id])
        ->and($response->json('data.lines'))->toContain('1 x COLA')
        ->and($response->json('data.lines'))->not->toContain('1 x LAVASH');
});

it('preserves unprinted state when the client does not confirm a failed print', function () {
    [$organization, $store, $user, , $order, $session] = kitchenContext();
    $item = kitchenItem($organization, $store, $order, $user, 'Soup');

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen")
        ->assertOk();

    expect($item->refresh()->kitchen_printed_at)->toBeNull()
        ->and($order->refresh()->exists)->toBeTrue();
});

it('marks reprint payloads and leaves kitchen timestamps unchanged', function () {
    [$organization, $store, $manager, , $order, $session] = kitchenContext(OrganizationRole::Manager);
    $printedAt = now()->subMinute();
    $item = kitchenItem($organization, $store, $order, $manager, 'Tea');
    $item->forceFill(['kitchen_printed_at' => $printedAt])->save();
    $persistedPrintedAt = $item->refresh()->kitchen_printed_at;

    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen/reprint")
        ->assertOk()
        ->assertJsonPath('data.is_reprint', true);

    expect($item->refresh()->kitchen_printed_at->equalTo($persistedPrintedAt))->toBeTrue();
});

it('enforces reprint permission and order ownership', function () {
    [, , $cashier, , $order, $session] = kitchenContext();
    $foreignOrder = Order::factory()->create();

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen/reprint")
        ->assertForbidden();
    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$foreignOrder->id}/send-kitchen")
        ->assertForbidden();
});

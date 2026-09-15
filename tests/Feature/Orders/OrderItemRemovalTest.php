<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrderStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintType;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemRemoval;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

function itemRemovalContext(bool $withShift = true, bool $withRole = true): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    if ($withRole) {
        app(OrganizationAuthorization::class)->runForUserInTenant(
            $user,
            $organization,
            fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Cashier->value),
        );
    }
    Subscription::factory()->for($organization)->create();
    $device = Device::factory()->for($organization)->for($store)->create();
    if ($withShift) {
        Shift::factory()->for($organization)->for($store)->for($device)->for($user)->create();
    }
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'subtotal' => 30000,
        'total' => 30000,
    ]);
    $item = OrderItem::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'product_name' => 'Lavash',
        'quantity' => 3,
        'unit_price' => 10000,
        'unit_cost' => 6000,
        'total' => 30000,
    ]);
    $session = [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ];

    return [$organization, $store, $user, $device, $order, $item, $session];
}

it('partially and fully removes unprinted items without deleting the original item', function () {
    [, , $user, , $order, $item, $session] = itemRemovalContext();

    $firstId = (string) Str::ulid();
    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", [
            'items' => [[
                'id' => $firstId,
                'order_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertOk()
        ->assertJsonPath('data.subtotal', 20000)
        ->assertJsonPath('data.total', 20000)
        ->assertJsonPath('data.items.0.original_quantity', 3)
        ->assertJsonPath('data.items.0.removed_quantity', 1)
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.pending_item_removals_count', 0);

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", [
            'items' => [[
                'id' => (string) Str::ulid(),
                'order_item_id' => $item->id,
                'quantity' => 2,
            ]],
        ])->assertOk()
        ->assertJsonPath('data.subtotal', 0)
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
        ->assertJsonCount(0, 'data.items');

    expect($item->refresh()->exists)->toBeTrue()
        ->and(OrderItemRemoval::query()->sum('quantity'))->toBe(3);
});

it('auto-cancels a zero-item order and still allows its kitchen cancellation ticket', function () {
    [$organization, $store, $user, $device, $order, $item, $session] = itemRemovalContext();
    $item->forceFill([
        'quantity' => 1,
        'total' => 10000,
        'kitchen_printed_at' => now(),
    ])->save();
    $order->forceFill(['subtotal' => 10000, 'total' => 10000])->save();
    $printer = Printer::factory()->for($organization)->for($store)->for($device)->create(['system_name' => 'Kitchen']);
    PrintRoute::factory()->for($organization)->for($store)->for($printer)->create(['print_type' => PrintType::KitchenTicket]);

    $response = $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", [
            'items' => [[
                'id' => (string) Str::ulid(),
                'order_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.pending_item_removals_count', 1);

    expect($order->refresh()->closed_at)->not->toBeNull();

    $ticket = $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen-removals")
        ->assertOk();

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen-removals/confirm", [
            'removal_ids' => $ticket->json('data.removal_ids'),
        ])->assertOk()->assertJsonPath('data.marked_printed', 1);

    expect($response->json('data.items'))->toBe([]);
});

it('is idempotent when the same removal identifier is retried', function () {
    [, , $user, , $order, $item, $session] = itemRemovalContext();
    $payload = ['items' => [[
        'id' => (string) Str::ulid(),
        'order_item_id' => $item->id,
        'quantity' => 1,
    ]]];

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", $payload)->assertOk();
    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", $payload)
        ->assertOk()->assertJsonPath('data.total', 20000);

    expect(OrderItemRemoval::query()->count())->toBe(1)
        ->and($order->refresh()->total)->toBe(20000);
});

it('prints only the remaining quantity for an unprinted kitchen item', function () {
    [$organization, $store, $user, $device, $order, $item, $session] = itemRemovalContext();
    $printer = Printer::factory()->for($organization)->for($store)->for($device)->create(['system_name' => 'Kitchen']);
    PrintRoute::factory()->for($organization)->for($store)->for($printer)->create(['print_type' => PrintType::KitchenTicket]);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/item-removals", [
        'items' => [[
            'id' => (string) Str::ulid(),
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]],
    ])->assertOk();

    $ticket = $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen")
        ->assertOk();

    expect($ticket->json('data.lines'))->toContain('2 x LAVASH');
});

it('creates and confirms a separate kitchen ticket for a printed item removal', function () {
    [$organization, $store, $user, $device, $order, $item, $session] = itemRemovalContext();
    $item->forceFill(['kitchen_printed_at' => now()])->save();
    $printer = Printer::factory()->for($organization)->for($store)->for($device)->create(['system_name' => 'Kitchen']);
    PrintRoute::factory()->for($organization)->for($store)->for($printer)->create(['print_type' => PrintType::KitchenTicket]);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/item-removals", [
        'items' => [[
            'id' => (string) Str::ulid(),
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]],
    ])->assertOk()->assertJsonPath('data.pending_item_removals_count', 1);

    $ticket = $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen-removals")
        ->assertOk()->assertJsonPath('data.is_reprint', false);

    expect(collect($ticket->json('data.lines'))->contains(
        fn (string $line): bool => str_contains($line, 'MAHSULOT BEKOR QILINDI'),
    ))->toBeTrue()
        ->and($ticket->json('data.lines'))->toContain('1 x LAVASH')
        ->and(OrderItemRemoval::query()->sole()->kitchen_printed_at)->toBeNull();

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/send-kitchen-removals/confirm", [
            'removal_ids' => $ticket->json('data.removal_ids'),
        ])->assertOk()->assertJsonPath('data.marked_printed', 1);

    expect(OrderItemRemoval::query()->sole()->kitchen_printed_at)->not->toBeNull();
});

it('rejects excessive removals and totals below existing payments', function () {
    [$organization, $store, $user, , $order, $item, $session] = itemRemovalContext();

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/item-removals", [
        'items' => [[
            'id' => (string) Str::ulid(),
            'order_item_id' => $item->id,
            'quantity' => 4,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');

    Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'method' => PaymentMethod::Cash,
        'amount' => 25000,
    ]);
    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/item-removals", [
        'items' => [[
            'id' => (string) Str::ulid(),
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');

    expect(OrderItemRemoval::query()->count())->toBe(0)
        ->and($order->refresh()->total)->toBe(30000);
});

it('recalculates payment status after reducing the order total', function () {
    [$organization, $store, $user, , $order, $item, $session] = itemRemovalContext();
    $order->forceFill(['payment_status' => PaymentStatus::PartiallyPaid])->save();
    Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'method' => PaymentMethod::Cash,
        'amount' => 20000,
    ]);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/item-removals", [
        'items' => [[
            'id' => (string) Str::ulid(),
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.total', 20000)
        ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.balance_due', 0);
});

it('requires an open shift, an open current-store order, and update permission', function () {
    [, , $user, , $order, $item, $session] = itemRemovalContext(withShift: false);
    $payload = ['items' => [[
        'id' => (string) Str::ulid(),
        'order_item_id' => $item->id,
        'quantity' => 1,
    ]]];

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('shift');

    Shift::factory()->for($order->organization)->for($order->store)->for($order->device)->for($user)->create();
    $order->forceFill(['status' => OrderStatus::Completed])->save();
    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('order');

    [, , $unauthorized, , $otherOrder, $otherItem, $unauthorizedSession] = itemRemovalContext(withRole: false);
    $this->actingAs($unauthorized)->withSession($unauthorizedSession)
        ->postJson("/api/pos/orders/{$otherOrder->id}/item-removals", [
            'items' => [[
                'id' => (string) Str::ulid(),
                'order_item_id' => $otherItem->id,
                'quantity' => 1,
            ]],
        ])->assertForbidden();
});

it('blocks removing an item through a different active store context', function () {
    [$organization, , $user, $device, $order, $item, $session] = itemRemovalContext();
    $otherStore = Store::factory()->for($organization)->create();
    $otherDevice = Device::factory()->for($organization)->for($otherStore)->create();
    $otherStore->users()->attach($user);
    $session['current_store_id'] = $otherStore->id;
    $session = array_merge($session, posDeviceSession($otherDevice));

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/item-removals", [
            'items' => [[
                'id' => (string) Str::ulid(),
                'order_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('order');

    expect(OrderItemRemoval::query()->count())->toBe(0)
        ->and($device->is($otherDevice))->toBeFalse();
});

it('blocks cross-tenant item removal', function () {
    [, , $user, , , , $session] = itemRemovalContext();
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();
    $foreignUser = User::factory()->create();
    $foreignOrder = Order::factory()->for($foreignOrganization)->for($foreignStore)->for($foreignUser, 'creator')->create();
    $foreignItem = OrderItem::factory()->for($foreignOrganization)->for($foreignStore)->for($foreignOrder)->for($foreignUser, 'creator')->create();

    $this->actingAs($user)->withSession($session)
        ->postJson("/api/pos/orders/{$foreignOrder->id}/item-removals", [
            'items' => [[
                'id' => (string) Str::ulid(),
                'order_item_id' => $foreignItem->id,
                'quantity' => 1,
            ]],
        ])->assertForbidden();

    expect(OrderItemRemoval::query()->count())->toBe(0);
});

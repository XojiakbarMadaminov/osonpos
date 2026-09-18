<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\OrganizationRole;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;

function tableMoveContext(Organization $organization, Store $store): array
{
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

    return [$user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

it('moves an open dine-in order to a free table without changing kitchen print state', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, $device, $session] = tableMoveContext($organization, $store);
    $source = Table::factory()->for($organization)->for($store)->create(['name' => 'Stol 1', 'number' => '1']);
    $target = Table::factory()->for($organization)->for($store)->create(['name' => 'Stol 3', 'number' => '3']);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'type' => OrderType::DineIn,
        'status' => OrderStatus::Open,
        'table_id' => $source->id,
    ]);
    $item = OrderItem::factory()->for($organization)->for($store)->for($order)->create([
        'kitchen_printed_at' => now()->subMinute(),
    ]);
    $printedAt = $item->kitchen_printed_at;

    $this->actingAs($user)->withSession($session)
        ->patchJson("/api/pos/orders/{$order->id}/table", ['table_id' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.table_id', $target->id)
        ->assertJsonPath('data.table.id', $target->id)
        ->assertJsonPath('data.table.number', '3');

    expect($order->refresh()->table_id)->toBe($target->id)
        ->and($item->refresh()->kitchen_printed_at->equalTo($printedAt))->toBeTrue();

    $bootstrap = $this->getJson('/api/pos/bootstrap')->assertOk();
    $tables = collect($bootstrap->json('data.tables'))->keyBy('id');

    expect($tables[$source->id]['is_occupied'])->toBeFalse()
        ->and($tables[$target->id]['is_occupied'])->toBeTrue()
        ->and($tables[$target->id]['open_order_id'])->toBe($order->id);
});

it('rejects an occupied inactive or foreign-store destination table', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($organization)->create();
    [$user, $device, $session] = tableMoveContext($organization, $store);
    $source = Table::factory()->for($organization)->for($store)->create();
    $occupied = Table::factory()->for($organization)->for($store)->create();
    $inactive = Table::factory()->for($organization)->for($store)->create(['is_active' => false]);
    $foreign = Table::factory()->for($organization)->for($otherStore)->create();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'type' => OrderType::DineIn,
        'table_id' => $source->id,
    ]);
    Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'type' => OrderType::DineIn,
        'status' => OrderStatus::Open,
        'table_id' => $occupied->id,
    ]);

    $this->actingAs($user)->withSession($session)
        ->patchJson("/api/pos/orders/{$order->id}/table", ['table_id' => $occupied->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.table_id.0', 'Tanlangan stol band. Boshqa bo‘sh stolni tanlang.');

    foreach ([$inactive, $foreign] as $invalidTable) {
        $this->patchJson("/api/pos/orders/{$order->id}/table", ['table_id' => $invalidTable->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.table_id.0', 'Joriy filialdan faol stolni tanlang.');
    }

    expect($order->refresh()->table_id)->toBe($source->id);
});

it('moves only open dine-in orders and requires order update permission', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, $device, $session] = tableMoveContext($organization, $store);
    $target = Table::factory()->for($organization)->for($store)->create();
    $takeaway = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'type' => OrderType::Takeaway,
        'table_id' => null,
    ]);
    $completed = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'type' => OrderType::DineIn,
        'status' => OrderStatus::Completed,
        'table_id' => Table::factory()->for($organization)->for($store)->create()->id,
    ]);

    foreach ([$takeaway, $completed] as $invalidOrder) {
        $this->actingAs($user)->withSession($session)
            ->patchJson("/api/pos/orders/{$invalidOrder->id}/table", ['table_id' => $target->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    $unauthorized = User::factory()->create();
    $organization->users()->attach($unauthorized);
    $store->users()->attach($unauthorized);
    $source = Table::factory()->for($organization)->for($store)->create();
    $openOrder = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'type' => OrderType::DineIn,
        'table_id' => $source->id,
    ]);

    $this->actingAs($unauthorized)->withSession($session)
        ->patchJson("/api/pos/orders/{$openOrder->id}/table", ['table_id' => $target->id])
        ->assertForbidden();

    expect($openOrder->refresh()->table_id)->toBe($source->id);
});

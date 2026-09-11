<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\OrganizationRole;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Str;

function orderUser(Organization $organization, Store $store, OrganizationRole $role = OrganizationRole::Cashier): array
{
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

    return [$user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        'current_device_id' => $device->id,
    ]];
}

function orderProduct(Organization $organization, array $attributes = []): Product
{
    $category = Category::factory()->for($organization)->create();

    return Product::factory()->for($organization)->for($category)->create($attributes);
}

it('creates an unpaid open dine-in order and snapshots added products', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, $device, $session] = orderUser($organization, $store);
    $table = Table::factory()->for($organization)->for($store)->create();
    $product = orderProduct($organization, ['name' => 'Original Plov', 'price' => 65000]);

    $orderResponse = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::DineIn->value,
        'table_id' => $table->id,
    ])->assertCreated()
        ->assertJsonPath('data.table.id', $table->id)
        ->assertJsonPath('data.table.number', $table->number);
    $order = Order::query()->findOrFail($orderResponse->json('data.id'));

    expect($order->status)->toBe(OrderStatus::Open)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->device_id)->toBe($device->id)
        ->and($order->table_id)->toBe($table->id);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/items", [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertCreated();
    $product->update(['name' => 'Renamed Plov', 'price' => 80000]);
    $item = OrderItem::query()->sole();

    expect($item->product_name)->toBe('Original Plov')
        ->and($item->unit_price)->toBe(65000)
        ->and($item->total)->toBe(130000)
        ->and($order->refresh()->subtotal)->toBe(130000)
        ->and($order->total)->toBe(130000);

    $this->actingAs($user)->withSession($session)
        ->getJson('/api/pos/orders')
        ->assertOk()
        ->assertJsonPath('data.0.table.id', $table->id)
        ->assertJsonPath('data.0.table.number', $table->number);
});

it('creates takeaway orders with sequential store display numbers', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);

    $first = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
    ])->assertCreated();
    $second = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
    ])->assertCreated();

    expect($first->json('data.display_number'))->toBe('#0001')
        ->and($second->json('data.display_number'))->toBe('#0002');
});

it('creates delivery orders with a reusable customer and address snapshot', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);

    $response = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Delivery->value,
        'customer' => ['phone' => '+998 90 123-45-67', 'name' => 'Ali'],
        'delivery' => ['address' => '12 Original Street', 'fee' => 15000],
    ])->assertCreated();
    $order = Order::query()->findOrFail($response->json('data.id'));
    $customer = Customer::query()->sole();

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Delivery->value,
        'customer' => ['phone' => '+998901234567'],
        'delivery' => ['address' => '99 New Street', 'fee' => 5000],
    ])->assertCreated();

    expect(Customer::query()->count())->toBe(1)
        ->and($order->customer_id)->toBe($customer->id)
        ->and($order->deliveryDetail->address)->toBe('12 Original Street')
        ->and($order->delivery_fee)->toBe(15000)
        ->and($order->total)->toBe(15000);
});

it('makes order and item creation replay-safe by client ULID', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);
    $product = orderProduct($organization);
    $orderId = (string) Str::ulid();
    $itemId = (string) Str::ulid();
    $payload = ['id' => $orderId, 'type' => OrderType::Takeaway->value];

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', $payload)->assertCreated();
    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', $payload)->assertOk();
    $itemPayload = ['id' => $itemId, 'product_id' => $product->id, 'quantity' => 1];
    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$orderId}/items", $itemPayload)->assertCreated();
    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$orderId}/items", $itemPayload)->assertCreated();

    expect(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(1);
});

it('rejects tables from another store and customers from another tenant', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);
    $otherTable = Table::factory()->for($organization)->for($otherStore)->create();
    $otherCustomer = Customer::factory()->create();

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::DineIn->value,
        'table_id' => $otherTable->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('table_id');
    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Delivery->value,
        'customer_id' => $otherCustomer->id,
        'delivery' => ['address' => 'Address', 'fee' => 0],
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
});

it('blocks cross-tenant replay and cross-store order mutation', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);
    $foreignOrder = Order::factory()->create();

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'id' => $foreignOrder->id,
        'type' => OrderType::Takeaway->value,
    ])->assertUnprocessable()->assertJsonValidationErrors('id');
    $this->actingAs($user)->withSession($session)->patchJson("/api/pos/orders/{$foreignOrder->id}", [
        'note' => 'tamper',
    ])->assertForbidden();

    expect($foreignOrder->refresh()->note)->toBeNull();
});

it('updates and cancels only open orders with the required permissions', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$manager, , $session] = orderUser($organization, $store, OrganizationRole::Manager);
    $order = Order::factory()->for($organization)->for($store)->for($manager, 'creator')->create();

    $this->actingAs($manager)->withSession($session)->patchJson("/api/pos/orders/{$order->id}", [
        'note' => 'Updated note',
    ])->assertOk()->assertJsonPath('data.note', 'Updated note');
    $this->actingAs($manager)->withSession($session)->postJson("/api/pos/orders/{$order->id}/cancel")
        ->assertOk()->assertJsonPath('data.status', OrderStatus::Cancelled->value);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->closed_by)->toBe($manager->id)
        ->and($order->closed_at)->not->toBeNull();
});

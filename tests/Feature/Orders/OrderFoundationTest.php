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
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

function orderUser(
    Organization $organization,
    Store $store,
    OrganizationRole $role = OrganizationRole::Cashier,
    bool $withShift = true,
): array {
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
    if ($withShift) {
        Shift::factory()->for($organization)->for($store)->for($device)->for($user)->create();
    }

    return [$user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

it('requires the current cashier shift for every order type', function (OrderType $type) {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store, withShift: false);
    $table = $type === OrderType::DineIn ? Table::factory()->for($organization)->for($store)->create() : null;
    $payload = ['type' => $type->value];

    if ($table) {
        $payload['table_id'] = $table->id;
    }
    if ($type === OrderType::Delivery) {
        $payload['customer'] = ['phone' => '+998901234567'];
        $payload['delivery'] = ['address' => 'Toshkent', 'fee' => 0];
    }

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shift')
        ->assertJsonPath('errors.shift.0', 'Buyurtma olish uchun avval smenani oching.');

    expect(Order::query()->count())->toBe(0);
})->with(OrderType::cases());

it('does not use another device shift to create an order', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, $device, $session] = orderUser($organization, $store, withShift: false);
    $otherDevice = Device::factory()->for($organization)->for($store)->create();
    Shift::factory()->for($organization)->for($store)->for($otherDevice)->for($user)->create();

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
    ])->assertUnprocessable()->assertJsonValidationErrors('shift');

    expect(Order::query()->count())->toBe(0)
        ->and($device->is($otherDevice))->toBeFalse();
});

it('requires the current cashier shift when adding a product to an order', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, $device, $session] = orderUser($organization, $store, withShift: false);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create();
    $product = orderProduct($organization);

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/items", [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('shift')
        ->assertJsonPath('errors.shift.0', 'Buyurtmaga mahsulot qo‘shish uchun avval smenani oching.');

    expect(OrderItem::query()->count())->toBe(0);
});

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
    $product = orderProduct($organization, ['name' => 'Original Plov', 'price' => 65000, 'cost_price' => 40000]);

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
    ])->assertCreated()
        ->assertJsonMissingPath('data.unit_cost');
    $product->update(['name' => 'Renamed Plov', 'price' => 80000, 'cost_price' => 55000]);
    $item = OrderItem::query()->sole();

    expect($item->product_name)->toBe('Original Plov')
        ->and($item->unit_price)->toBe(65000)
        ->and($item->unit_cost)->toBe(40000)
        ->and($item->total)->toBe(130000)
        ->and($order->refresh()->subtotal)->toBe(130000)
        ->and($order->total)->toBe(130000);

    $this->actingAs($user)->withSession($session)
        ->getJson('/api/pos/orders')
        ->assertOk()
        ->assertJsonMissingPath('data.0.items.0.unit_cost')
        ->assertJsonPath('data.0.table.id', $table->id)
        ->assertJsonPath('data.0.table.number', $table->number);
});

it('rejects a product from another store in the same organization', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($organization)->create();
    [$user, $device, $session] = orderUser($organization, $store);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create();
    $otherCategory = Category::factory()->for($organization)->for($otherStore)->create();
    $otherProduct = Product::factory()->for($organization)->for($otherCategory)->create();

    $this->actingAs($user)->withSession($session)->postJson("/api/pos/orders/{$order->id}/items", [
        'product_id' => $otherProduct->id,
        'quantity' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('product_id')
        ->assertJsonPath('errors.product_id.0', 'Joriy filialdan faol mahsulotni tanlang.');

    expect(OrderItem::query()->count())->toBe(0);
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
        ->and($second->json('data.display_number'))->toBe('#0002')
        ->and(Order::query()->latest('opened_at')->first()->business_date->toDateString())
        ->toBe(now($store->timezone)->toDateString());
});

it('creates a takeaway order with an optional existing customer snapshot', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);
    $customer = Customer::factory()->for($organization)->create([
        'name' => 'Xojiakbar',
        'phone' => '+998901234567',
    ]);

    $response = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
        'customer_id' => $customer->id,
    ])->assertCreated()
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.customer.name', 'Xojiakbar')
        ->assertJsonPath('data.customer.phone', '+998901234567');

    $order = Order::query()->findOrFail($response->json('data.id'));
    $customer->update(['name' => 'Yangi ism', 'phone' => '+998909999999']);

    expect($order->refresh()->customer_name)->toBe('Xojiakbar')
        ->and($order->customer_phone)->toBe('+998901234567');
});

it('creates and reuses a normalized customer from the POS before a takeaway order', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, , $session] = orderUser($organization, $store);

    $first = $this->actingAs($user)->withSession($session)->postJson('/api/pos/customers', [
        'phone' => '90 123-45-67',
        'name' => 'Ali',
    ])->assertOk()->assertJsonPath('data.phone', '+998901234567');

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/customers', [
        'phone' => '+998 (90) 123 45 67',
        'name' => 'Boshqa ism',
    ])->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
        'customer_id' => $first->json('data.id'),
    ])->assertCreated()->assertJsonPath('data.customer.name', 'Ali');

    expect(Customer::query()->count())->toBe(1);
});

it('attaches replaces and removes a customer on an open dine-in order', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    [$user, $device, $session] = orderUser($organization, $store);
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'type' => OrderType::DineIn,
    ]);
    $first = Customer::factory()->for($organization)->create(['name' => 'Birinchi', 'phone' => '+998901111111']);
    $second = Customer::factory()->for($organization)->create(['name' => 'Ikkinchi', 'phone' => '+998902222222']);

    $this->actingAs($user)->withSession($session)->putJson("/api/pos/orders/{$order->id}/customer", [
        'customer_id' => $first->id,
    ])->assertOk()->assertJsonPath('data.customer.name', 'Birinchi');

    $this->putJson("/api/pos/orders/{$order->id}/customer", [
        'customer_id' => $second->id,
    ])->assertOk()->assertJsonPath('data.customer.name', 'Ikkinchi');

    $this->deleteJson("/api/pos/orders/{$order->id}/customer")
        ->assertOk()->assertJsonPath('data.customer', null);

    expect($order->refresh()->customer_id)->toBeNull()
        ->and($order->customer_name)->toBeNull()
        ->and($order->customer_phone)->toBeNull()
        ->and(Customer::query()->count())->toBe(2);
});

it('rejects customer changes on completed or cross-store orders', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($organization)->create();
    [$user, $device, $session] = orderUser($organization, $store);
    $otherStore->users()->attach($user);
    $customer = Customer::factory()->for($organization)->create();
    $completed = Order::factory()->for($organization)->for($store)->for($device)->for($user, 'creator')->create([
        'status' => OrderStatus::Completed,
    ]);
    $otherStoreOrder = Order::factory()->for($organization)->for($otherStore)->for($user, 'creator')->create();

    $this->actingAs($user)->withSession($session)->putJson("/api/pos/orders/{$completed->id}/customer", [
        'customer_id' => $customer->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('order');

    $this->putJson("/api/pos/orders/{$otherStoreOrder->id}/customer", [
        'customer_id' => $customer->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('order');
});

it('blocks a foreign tenant customer but permits an organization customer from another store', function () {
    $organization = Organization::factory()->create();
    $firstStore = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($organization)->create();
    [$user] = orderUser($organization, $firstStore, withShift: false);
    $otherStore->users()->attach($user);
    $device = Device::factory()->for($organization)->for($otherStore)->create();
    $session = [
        'current_organization_id' => $organization->id,
        'current_store_id' => $otherStore->id,
        ...posDeviceSession($device),
    ];
    $order = Order::factory()->for($organization)->for($otherStore)->for($device)->for($user, 'creator')->create();
    $sharedCustomer = Customer::factory()->for($organization)->create();
    $foreignCustomer = Customer::factory()->create();

    $this->actingAs($user)->withSession($session)->putJson("/api/pos/orders/{$order->id}/customer", [
        'customer_id' => $sharedCustomer->id,
    ])->assertOk()->assertJsonPath('data.customer.id', $sharedCustomer->id);

    $this->putJson("/api/pos/orders/{$order->id}/customer", [
        'customer_id' => $foreignCustomer->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');

    expect($order->refresh()->customer_id)->toBe($sharedCustomer->id);
});

it('shows current business date history and older open orders first', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 04:30:00', 'Asia/Tashkent'));
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['timezone' => 'America/New_York']);
    [$user, , $session] = orderUser($organization, $store);

    $today = Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-14',
        'status' => OrderStatus::Completed,
        'opened_at' => now(),
    ]);
    $yesterday = Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-13',
        'status' => OrderStatus::Completed,
        'opened_at' => now()->subDay(),
    ]);
    $olderOpen = Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'display_number' => '#0002',
        'business_date' => '2026-09-13',
        'status' => OrderStatus::Open,
        'opened_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->withSession($session)
        ->getJson('/api/pos/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $olderOpen->id)
        ->assertJsonPath('data.0.business_date', '2026-09-13')
        ->assertJsonPath('data.0.is_current_business_date', false)
        ->assertJsonPath('data.0.opened_at', $olderOpen->opened_at->toIso8601String())
        ->assertJsonPath('data.1.id', $today->id)
        ->assertJsonPath('data.1.is_current_business_date', true)
        ->assertJsonMissing(['id' => $yesterday->id]);
});

it('restarts display numbers on each store-local business date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 04:30:00', 'Asia/Tashkent'));
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['timezone' => 'America/New_York']);
    [$user, , $session] = orderUser($organization, $store);

    $firstDay = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
    ])->assertCreated();

    $this->travelTo(CarbonImmutable::parse('2026-09-15 09:30:00', 'Asia/Tashkent'));
    $secondDay = $this->actingAs($user)->withSession($session)->postJson('/api/pos/orders', [
        'type' => OrderType::Takeaway->value,
    ])->assertCreated();

    expect($firstDay->json('data.display_number'))->toBe('#0001')
        ->and($secondDay->json('data.display_number'))->toBe('#0001')
        ->and(Order::query()->orderBy('business_date')->pluck('business_date')->map->toDateString()->all())
        ->toBe(['2026-09-14', '2026-09-15']);
});

it('prevents duplicate display numbers within the same store business date', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();

    Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-14',
    ]);

    expect(fn () => Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-14',
    ]))->toThrow(QueryException::class);
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
        ->and($order->customer_name)->toBe('Ali')
        ->and($order->customer_phone)->toBe('+998901234567')
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

<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Enums\PrintType;
use App\Models\Category;
use App\Models\Device;
use App\Models\Feature;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function bootstrapContext(): array
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
    $feature = Feature::factory()->create(['code' => 'pos']);
    $plan = Plan::factory()->create();
    $plan->features()->attach($feature);
    Subscription::factory()->for($organization)->for($plan)->create();
    $device = Device::factory()->for($organization)->for($store)->create();

    return [$organization, $store, $user, $device, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

it('loads all required POS base state in one bootstrap response', function () {
    [$organization, $store, $user, $device, $session] = bootstrapContext();
    $category = Category::factory()->for($organization)->create();
    $product = Product::factory()->for($organization)->for($category)->create();
    $table = Table::factory()->for($organization)->for($store)->create();
    $openOrder = Order::factory()->for($organization)->for($store)->for($table)->for($user, 'creator')->create();
    $printer = Printer::factory()->for($organization)->for($store)->for($device)->create();
    $route = PrintRoute::factory()->for($organization)->for($store)->for($printer)->create([
        'print_type' => PrintType::CustomerReceipt,
    ]);
    $shift = Shift::factory()->for($organization)->for($store)->for($device)->for($user)->create();

    $response = $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertOk();

    $response->assertJsonStructure(['data' => [
        'organization', 'store', 'device', 'user', 'permissions', 'features', 'categories', 'products',
        'tables', 'printers', 'print_routes', 'active_shift',
    ]]);
    expect($response->json('data.organization.id'))->toBe($organization->id)
        ->and($response->json('data.store.id'))->toBe($store->id)
        ->and($response->json('data.device.id'))->toBe($device->id)
        ->and(collect($response->json('data.products'))->pluck('id'))->toContain($product->id)
        ->and(collect($response->json('data.tables'))->pluck('id'))->toContain($table->id)
        ->and(collect($response->json('data.tables'))->firstWhere('id', $table->id)['is_occupied'])->toBeTrue()
        ->and(collect($response->json('data.tables'))->firstWhere('id', $table->id)['open_order_id'])->toBe($openOrder->id)
        ->and(collect($response->json('data.printers'))->pluck('id'))->toContain($printer->id)
        ->and(collect($response->json('data.print_routes'))->pluck('id'))->toContain($route->id)
        ->and($response->json('data.active_shift.id'))->toBe($shift->id);
});

it('returns only the current tenant and store data with permissions and features', function () {
    [$organization, $store, $user, , $session] = bootstrapContext();
    $otherStore = Store::factory()->for($organization)->create();
    $allowedTable = Table::factory()->for($organization)->for($store)->create();
    $otherStoreTable = Table::factory()->for($organization)->for($otherStore)->create();
    $foreignProduct = Product::factory()->create();

    $response = $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertOk();
    $tableIds = collect($response->json('data.tables'))->pluck('id');
    $productIds = collect($response->json('data.products'))->pluck('id');

    expect($tableIds)->toContain($allowedTable->id)
        ->and($tableIds)->not->toContain($otherStoreTable->id)
        ->and($productIds)->not->toContain($foreignProduct->id)
        ->and($response->json('data.permissions'))->toContain(OrganizationPermission::PosAccess->value)
        ->and($response->json('data.features'))->toBe(['pos']);
});

it('invalidates cached catalog and store configuration after admin-style changes', function () {
    [$organization, $store, $user, , $session] = bootstrapContext();
    $category = Category::factory()->for($organization)->create();

    $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertOk();
    $product = Product::factory()->for($organization)->for($category)->create();
    $table = Table::factory()->for($organization)->for($store)->create();

    $response = $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertOk();

    expect(collect($response->json('data.products'))->pluck('id'))->toContain($product->id)
        ->and(collect($response->json('data.tables'))->pluck('id'))->toContain($table->id);
});

it('keeps warm bootstrap queries bounded and reuses cached configuration', function () {
    [$organization, $store, $user, , $session] = bootstrapContext();
    $category = Category::factory()->for($organization)->create();
    Product::factory()->count(20)->for($organization)->for($category)->create();
    Table::factory()->count(10)->for($organization)->for($store)->create();
    Cache::flush();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertOk();
    $coldQueries = count(DB::getQueryLog());

    DB::flushQueryLog();
    $this->actingAs($user)->withSession($session)->getJson('/api/pos/bootstrap')->assertOk();
    $warmQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($warmQueries)->toBeLessThan($coldQueries)
        ->and($warmQueries)->toBeLessThanOrEqual(20);
});

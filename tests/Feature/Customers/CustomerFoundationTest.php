<?php

use App\Actions\Customers\FindOrCreateCustomer;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Models\Customer;
use App\Models\DeliveryDetail;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

function customerUser(Organization $organization, Store $store): User
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

    return $user;
}

it('reuses an existing customer by normalized phone within a tenant', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    app(TenantContext::class)->resolveFor($user, $organization->id);
    $action = app(FindOrCreateCustomer::class);

    $first = $action->execute('+998 90 123-45-67', 'Customer');
    $second = $action->execute('+998901234567', 'Different Name');

    expect($first->is($second))->toBeTrue()
        ->and(Customer::query()->count())->toBe(1)
        ->and($second->phone)->toBe('+998901234567')
        ->and($second->name)->toBe('Customer');
});

it('keeps identical phone numbers isolated by organization', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $user = User::factory()->create();
    $firstOrganization->users()->attach($user);
    $secondOrganization->users()->attach($user);
    $context = app(TenantContext::class);
    $action = app(FindOrCreateCustomer::class);

    $context->resolveFor($user, $firstOrganization->id);
    $first = $action->execute('+998901112233');
    $context->resolveFor($user, $secondOrganization->id);
    $second = $action->execute('+998901112233');

    expect($first->isNot($second))->toBeTrue()
        ->and($first->organization_id)->toBe($firstOrganization->id)
        ->and($second->organization_id)->toBe($secondOrganization->id);
});

it('looks up a customer by phone through the POS API', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = customerUser($organization, $store);
    $device = Device::factory()->for($organization)->for($store)->create();
    $customer = Customer::factory()->for($organization)->create(['phone' => '+998901234567']);

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
            ...posDeviceSession($device),
        ])
        ->getJson('/api/pos/customers/lookup?phone=%2B998%2090%20123-45-67')
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id);
});

it('does not expose another organization customer through phone lookup', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = customerUser($organization, $store);
    $device = Device::factory()->for($organization)->for($store)->create();
    Customer::factory()->create(['phone' => '+998901234567']);

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
            ...posDeviceSession($device),
        ])
        ->getJson('/api/pos/customers/lookup?phone=%2B998901234567')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('requires a phone for customer lookup', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = customerUser($organization, $store);
    $device = Device::factory()->for($organization)->for($store)->create();

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
            ...posDeviceSession($device),
        ])
        ->getJson('/api/pos/customers/lookup')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('stores an order-specific address snapshot and integer fee', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $firstOrder = Order::factory()->for($organization)->for($store)->create();
    $secondOrder = Order::factory()->for($organization)->for($store)->create();
    $detail = DeliveryDetail::factory()->for($organization)->for($store)->for($firstOrder, 'order')->create([
        'address' => '12 Original Street',
        'delivery_fee' => 15000,
    ]);
    DeliveryDetail::factory()->for($organization)->for($store)->for($secondOrder, 'order')->create([
        'address' => '99 New Street',
    ]);

    expect($detail->refresh()->address)->toBe('12 Original Street')
        ->and($detail->delivery_fee)->toBeInt()
        ->and($detail->delivery_fee)->toBe(15000);
});

it('rejects delivery details that cross tenant and store ownership', function () {
    $organization = Organization::factory()->create();
    $otherStore = Store::factory()->create();

    expect(fn () => DeliveryDetail::factory()->for($organization)->for($otherStore)->create())
        ->toThrow(ValidationException::class);
});

<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Models\Device;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

function deviceUser(Organization $organization, Store $store, OrganizationRole $role = OrganizationRole::Cashier): User
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

    return $user;
}

it('rejects model ownership across tenants', function () {
    $organization = Organization::factory()->create();
    $otherStore = Store::factory()->create();

    expect(fn () => Device::factory()->for($organization)->for($otherStore)->create())
        ->toThrow(ValidationException::class);
});

it('denies disabled devices from device-bound POS requests', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = deviceUser($organization, $store);
    $device = Device::factory()->inactive()->for($organization)->for($store)->create();

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
            ...posDeviceSession($device),
        ])
        ->getJson('/api/pos/device')
        ->assertForbidden();
});

it('does not trust a browser supplied device id header', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = deviceUser($organization, $store);
    $device = Device::factory()->for($organization)->for($store)->create();

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->withHeader('X-POS-Device-ID', $device->id)
        ->getJson('/api/pos/device')
        ->assertForbidden();
});

it('enforces device store access in policy checks', function () {
    $organization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    $deniedStore = Store::factory()->for($organization)->create();
    $manager = deviceUser($organization, $allowedStore, OrganizationRole::Manager);
    $deniedDevice = Device::factory()->for($organization)->for($deniedStore)->create();
    app(TenantContext::class)->resolveFor($manager, $organization->id);

    expect($manager->can('view', $deniedDevice))->toBeFalse();
});

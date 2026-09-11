<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Models\Device;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
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

it('registers a ULID device into the trusted current store', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = deviceUser($organization, $store);

    $response = $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->postJson('/api/pos/devices/register', [
            'name' => 'Main Cashier',
            'code' => 'main-pos',
            'organization_id' => Organization::factory()->create()->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Main Cashier')
        ->assertJsonPath('data.code', 'MAIN-POS')
        ->assertSessionHas('current_device_id');

    $device = Device::query()->sole();

    expect(Str::isUlid($device->id))->toBeTrue()
        ->and($device->organization_id)->toBe($organization->id)
        ->and($device->store_id)->toBe($store->id)
        ->and($device->last_seen_at)->not->toBeNull();
});

it('registers the same active device idempotently', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = deviceUser($organization, $store);
    $session = [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ];

    $this->actingAs($user)->withSession($session)->postJson('/api/pos/devices/register', [
        'name' => 'Cashier',
        'code' => 'POS-01',
    ])->assertCreated();
    $this->actingAs($user)->withSession($session)->postJson('/api/pos/devices/register', [
        'name' => 'Cashier Renamed',
        'code' => 'pos-01',
    ])->assertCreated();

    expect(Device::query()->count())->toBe(1)
        ->and(Device::query()->sole()->name)->toBe('Cashier Renamed');
});

it('does not allow a device code to cross stores in an organization', function () {
    $organization = Organization::factory()->create();
    $firstStore = Store::factory()->for($organization)->create();
    $secondStore = Store::factory()->for($organization)->create();
    $user = deviceUser($organization, $secondStore);
    Device::factory()->for($organization)->for($firstStore)->create(['code' => 'POS-01']);

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $secondStore->id,
        ])
        ->postJson('/api/pos/devices/register', [
            'name' => 'Other Store POS',
            'code' => 'POS-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

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
            'current_device_id' => $device->id,
        ])
        ->getJson('/api/pos/device')
        ->assertForbidden();
});

it('restores a registered device from its persistent browser header', function () {
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
        ->assertOk()
        ->assertJsonPath('data.id', $device->id);
});

it('rejects a persistent device header from another organization', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = deviceUser($organization, $store);
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();
    $foreignDevice = Device::factory()->for($foreignOrganization)->for($foreignStore)->create();

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->withHeader('X-POS-Device-ID', $foreignDevice->id)
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

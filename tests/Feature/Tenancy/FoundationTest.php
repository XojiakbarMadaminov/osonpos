<?php

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'context.tenant', 'context.store'])
        ->get('/_test/current-context', function (TenantContext $tenantContext, StoreContext $storeContext) {
            return response()->json([
                'organization_id' => $tenantContext->id(),
                'store_id' => $storeContext->id(),
            ]);
        });
});

it('allows a user to belong to multiple organizations', function () {
    $user = User::factory()->create();
    $organizations = Organization::factory()->count(2)->create();

    $user->organizations()->attach($organizations);

    expect($user->organizations()->pluck('organizations.id')->all())
        ->toEqualCanonicalizing($organizations->modelKeys());
});

it('allows a user to be restricted to selected stores', function () {
    $organization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    Store::factory()->for($organization)->create();
    $user = User::factory()->create();

    $organization->users()->attach($user);
    $allowedStore->users()->attach($user);

    expect($user->stores()->pluck('stores.id')->all())->toBe([$allowedStore->id]);
});

it('resolves tenant and store context from validated memberships', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->getJson('/_test/current-context')
        ->assertOk()
        ->assertExactJson([
            'organization_id' => $organization->id,
            'store_id' => $store->id,
        ]);
});

it('ignores tenant and store IDs supplied as request input', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $otherStore = Store::factory()->for($otherOrganization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->getJson("/_test/current-context?organization_id={$otherOrganization->id}&store_id={$otherStore->id}")
        ->assertExactJson([
            'organization_id' => $organization->id,
            'store_id' => $store->id,
        ]);
});

it('rejects a selected organization outside the user membership', function () {
    $allowedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($allowedOrganization)->create();
    $user = User::factory()->create();
    $allowedOrganization->users()->attach($user);
    $allowedStore->users()->attach($user);

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $otherOrganization->id])
        ->getJson('/_test/current-context')
        ->assertForbidden();
});

it('rejects a selected store outside the current organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($otherOrganization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $allowedStore->users()->attach($user);
    $otherStore->users()->attach($user);

    $this->actingAs($user)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $otherStore->id,
        ])
        ->getJson('/_test/current-context')
        ->assertForbidden();
});

it('provides tenant-scoped model helpers', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    Store::factory()->for($otherOrganization)->create();

    $tenantContext = app(TenantContext::class);
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $tenantContext->resolveFor($user, $organization->id);

    expect(Store::query()->forTenant($organization)->pluck('id')->all())->toBe([$store->id])
        ->and($store->belongsToTenant($organization))->toBeTrue()
        ->and($tenantContext->owns($store))->toBeTrue();
});

it('keeps relationship ownership authoritative during store creation', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $store = $organization->stores()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Main Store',
        'timezone' => 'Asia/Tashkent',
    ]);

    expect($store->organization_id)->toBe($organization->id);
});

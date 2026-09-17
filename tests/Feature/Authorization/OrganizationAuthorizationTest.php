<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'context.tenant'])
        ->get('/_test/authorize/store/{store}', function (Store $store) {
            Gate::authorize('view', $store);

            return response()->noContent();
        });

    Route::middleware(['web', 'auth', 'context.tenant'])
        ->get('/_test/access/store/{store}', function (Store $store, StoreAccess $storeAccess) {
            abort_unless($storeAccess->allows(request()->user(), $store), 403);

            return response()->noContent();
        });
});

it('creates the default organization roles and permission matrix', function () {
    $organization = Organization::factory()->create();

    $roles = app(CreateDefaultOrganizationRoles::class)->execute($organization);

    expect($roles->keys()->all())->toBe([
        OrganizationRole::Owner->value,
        OrganizationRole::Manager->value,
        OrganizationRole::Cashier->value,
        OrganizationRole::Waiter->value,
    ])->and($roles[OrganizationRole::Owner->value]->permissions()->count())
        ->toBe(count(OrganizationPermission::cases()))
        ->and($roles[OrganizationRole::Cashier->value]->hasPermissionTo(OrganizationPermission::PaymentsCreate->value))
        ->toBeTrue()
        ->and($roles[OrganizationRole::Cashier->value]->hasPermissionTo(OrganizationPermission::RolesManage->value))
        ->toBeFalse();
});

it('reuses database permissions when the Spatie permission cache is stale', function () {
    $permissionRegistrar = app(PermissionRegistrar::class);
    $permissionRegistrar->forgetCachedPermissions();
    $permissionRegistrar->getPermissions();

    $now = now();
    DB::table(config('permission.table_names.permissions'))->insertOrIgnore(
        collect(OrganizationPermission::cases())
            ->map(fn (OrganizationPermission $permission): array => [
                'name' => $permission->value,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all(),
    );

    $organization = Organization::factory()->create();
    $roles = app(CreateDefaultOrganizationRoles::class)->execute($organization);

    expect($roles[OrganizationRole::Owner->value]->permissions()->count())
        ->toBe(count(OrganizationPermission::cases()))
        ->and(DB::table(config('permission.table_names.permissions'))->count())
        ->toBe(count(OrganizationPermission::cases()));
});

it('assigns the owner membership and organization-scoped role', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();

    app(AssignOrganizationOwner::class)->execute($organization, $owner);

    $authorization = app(OrganizationAuthorization::class);

    expect($organization->users()->whereKey($owner->id)->exists())->toBeTrue()
        ->and($authorization->runForUserInTenant(
            $owner,
            $organization,
            fn (User $user): bool => $user->hasRole(OrganizationRole::Owner->value),
        ))->toBeTrue();
});

it('supports different roles for the same user in different organizations', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach([$firstOrganization->id, $secondOrganization->id]);

    $roleCreator = app(CreateDefaultOrganizationRoles::class);
    $roleCreator->execute($firstOrganization);
    $roleCreator->execute($secondOrganization);

    $authorization = app(OrganizationAuthorization::class);
    $authorization->runForUserInTenant(
        $user,
        $firstOrganization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Owner->value),
    );
    $authorization->runForUserInTenant(
        $user,
        $secondOrganization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Cashier->value),
    );

    expect($authorization->runForUserInTenant(
        $user,
        $firstOrganization,
        fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
    ))->toBeTrue()
        ->and($authorization->runForUserInTenant(
            $user,
            $secondOrganization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Cashier->value),
        ))->toBeTrue()
        ->and($authorization->runForUserInTenant(
            $user,
            $secondOrganization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        ))->toBeFalse();
});

it('denies the Shield role manager to a cashier through a direct request', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $store->users()->attach($cashier);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $cashier,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Cashier->value),
    );

    $this->actingAs($cashier)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/shield/roles')
        ->assertForbidden();
});

it('allows an owner to use the Shield role manager', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    $managerRole = Role::query()
        ->where('organization_id', $organization->id)
        ->where('name', OrganizationRole::Manager->value)
        ->sole();

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/shield/roles')
        ->assertOk()
        ->assertSee(OrganizationRole::Owner->label());

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/shield/roles/create')
        ->assertOk();

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/admin/shield/roles/{$managerRole->getKey()}/edit")
        ->assertOk();
});

it('does not resolve another tenant role through a direct Shield URL', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    $otherRoles = app(CreateDefaultOrganizationRoles::class)->execute($otherOrganization);
    $otherRole = $otherRoles[OrganizationRole::Owner->value];

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/admin/shield/roles/{$otherRole->getKey()}/edit")
        ->assertNotFound();
});

it('enforces store assignment for non-owner roles', function () {
    $organization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    $deniedStore = Store::factory()->for($organization)->create();
    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $allowedStore->users()->attach($cashier);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $cashier,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Cashier->value),
    );

    $session = ['current_organization_id' => $organization->id];

    $this->actingAs($cashier)
        ->withSession($session)
        ->get("/_test/access/store/{$allowedStore->id}")
        ->assertNoContent();

    $this->actingAs($cashier)
        ->withSession($session)
        ->get("/_test/access/store/{$deniedStore->id}")
        ->assertForbidden();
});

it('denies policies for resources belonging to another tenant', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($otherOrganization)->create();
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/_test/authorize/store/{$otherStore->id}")
        ->assertForbidden();
});

it('keeps organization roles isolated at the database level', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(CreateDefaultOrganizationRoles::class)->execute($otherOrganization);

    expect(Role::query()->where('organization_id', $organization->id)->count())->toBe(4)
        ->and(Role::query()->where('organization_id', $otherOrganization->id)->count())->toBe(4);
});

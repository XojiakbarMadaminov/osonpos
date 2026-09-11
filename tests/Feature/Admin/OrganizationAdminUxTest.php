<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Actions\Organizations\SaveOrganizationUser;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Filament\Admin\Resources\Tables\TableResource;
use App\Http\Middleware\InitializeTenantContext;
use App\Models\Feature;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

function adminOwner(Organization $organization, Store $store): User
{
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);

    return $owner;
}

it('persists tenant authorization context across filament livewire actions', function () {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(InitializeTenantContext::class);
});

it('keeps store order and user listings inside the current organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['name' => 'Current Branch']);
    $otherStore = Store::factory()->for($otherOrganization)->create(['name' => 'Foreign Branch']);
    $owner = adminOwner($organization, $store);
    $otherUser = User::factory()->create(['name' => 'Foreign Person']);
    $otherOrganization->users()->attach($otherUser);

    Order::factory()->for($organization)->for($store)->create([
        'display_number' => '#LOCAL-18',
        'created_by' => $owner,
    ]);
    Order::factory()->for($otherOrganization)->for($otherStore)->create([
        'display_number' => '#FOREIGN-18',
        'created_by' => $otherUser,
    ]);

    $session = ['current_organization_id' => $organization->id];

    $this->actingAs($owner)->withSession($session)->get('/admin/stores')
        ->assertOk()->assertSee('Current Branch')->assertDontSee('Foreign Branch');
    $this->actingAs($owner)->withSession($session)->get('/admin/orders')
        ->assertOk()->assertSee('#LOCAL-18')->assertDontSee('#FOREIGN-18');
    $this->actingAs($owner)->withSession($session)->get('/admin/users')
        ->assertOk()->assertSee($owner->name)->assertDontSee('Foreign Person');
});

it('limits store-owned admin listings to stores assigned to a manager', function () {
    $organization = Organization::factory()->create();
    $assigned = Store::factory()->for($organization)->create(['name' => 'Assigned Branch']);
    $unassigned = Store::factory()->for($organization)->create(['name' => 'Hidden Branch']);
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $assigned->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Manager->value),
    );

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/stores')
        ->assertOk()
        ->assertSee('Assigned Branch')
        ->assertDontSee('Hidden Branch');

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/admin/stores/{$unassigned->id}/edit")
        ->assertNotFound();
});

it('rejects user assignment to a store outside the current tenant', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $foreignStore = Store::factory()->create();
    $owner = adminOwner($organization, $store);
    app(TenantContext::class)->resolveFor($owner, $organization->id);
    $role = $organization->roles()->where('name', OrganizationRole::Cashier->value)->sole();

    expect(fn () => app(SaveOrganizationUser::class)->execute(
        $owner,
        null,
        ['name' => 'New User', 'email' => 'new-user@example.test', 'password' => 'password'],
        $role->id,
        [$foreignStore->id],
    ))->toThrow(ValidationException::class);

    expect(User::query()->where('email', 'new-user@example.test')->exists())->toBeFalse();
});

it('assigns a new organization user to an allowed role and stores', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);
    $plan = Plan::factory()->create(['max_users' => 5]);
    Subscription::factory()->for($organization)->for($plan)->create();
    app(TenantContext::class)->resolveFor($owner, $organization->id);
    $role = $organization->roles()->where('name', OrganizationRole::Cashier->value)->sole();

    $member = app(SaveOrganizationUser::class)->execute(
        $owner,
        null,
        ['name' => 'Cashier One', 'email' => 'cashier-one@example.test', 'password' => 'password'],
        $role->id,
        [$store->id],
    );

    expect($member->organizations()->whereKey($organization)->exists())->toBeTrue()
        ->and($member->stores()->whereKey($store)->exists())->toBeTrue()
        ->and(app(OrganizationAuthorization::class)->runForUserInTenant(
            $member,
            $organization,
            fn (User $user): bool => $user->hasRole(OrganizationRole::Cashier->value),
        ))->toBeTrue();
});

it('lets an owner switch to any active store in the organization', function () {
    $organization = Organization::factory()->create();
    $firstStore = Store::factory()->for($organization)->create();
    $secondStore = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $firstStore);
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($owner, $organization->id);

    $resolved = app(StoreContext::class)->resolveFor($owner, $tenantContext, $secondStore->id);

    expect($resolved->is($secondStore))->toBeTrue();
});

it('hides table navigation when the subscription does not include tables', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);
    $plan = Plan::factory()->create();
    Subscription::factory()->for($organization)->for($plan)->create();
    $this->actingAs($owner);
    app(TenantContext::class)->resolveFor($owner, $organization->id);

    expect(TableResource::shouldRegisterNavigation())->toBeFalse();

    $tables = Feature::factory()->create(['code' => 'tables']);
    $plan->features()->attach($tables);

    expect(TableResource::shouldRegisterNavigation())->toBeTrue();
});

it('keeps the report page behind reports permission', function () {
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
        ->get('/admin/reports')
        ->assertForbidden();
});

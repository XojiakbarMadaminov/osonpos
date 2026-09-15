<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Subscription\FeaturePermissionAccess;
use App\Domain\Subscription\PlanLimits;
use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'context.tenant', 'subscription.active'])
        ->post('/_test/pos-transaction', fn () => response()->noContent());

    Route::middleware(['web', 'auth', 'context.tenant'])
        ->get('/_test/feature/{feature}', function (string $feature, FeaturePermissionAccess $access) {
            abort_unless(
                $access->allows(request()->user(), $feature, OrganizationPermission::OrdersCreate),
                403,
            );

            return response()->noContent();
        });
});

it('resolves an active subscription and its plan features', function () {
    $organization = Organization::factory()->create();
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['code' => 'pos']);
    $plan->features()->attach($feature);
    $subscription = Subscription::factory()->for($organization)->for($plan)->create();

    $access = app(SubscriptionAccess::class);

    expect($access->activeSubscription($organization)->is($subscription))->toBeTrue()
        ->and($access->hasFeature($organization, 'pos'))->toBeTrue()
        ->and($access->hasFeature($organization, 'delivery'))->toBeFalse();
});

it('blocks new POS transactions for an expired subscription', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    Subscription::factory()->for($organization)->create([
        'status' => SubscriptionStatus::Expired,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $organization->id])
        ->post('/_test/pos-transaction')
        ->assertForbidden();
});

it('allows new POS transactions for an active subscription', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    Subscription::factory()->for($organization)->create();

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $organization->id])
        ->post('/_test/pos-transaction')
        ->assertNoContent();
});

it('keeps admin read access available after subscription expiry', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    Subscription::factory()->for($organization)->create([
        'status' => SubscriptionStatus::Expired,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $organization->id, 'current_store_id' => $store->id])
        ->get('/admin')
        ->assertRedirect('/pos');
});

it('prevents multiple current subscription records for one organization', function () {
    $organization = Organization::factory()->create();
    Subscription::factory()->for($organization)->create();

    expect(fn () => Subscription::factory()->for($organization)->create([
        'status' => SubscriptionStatus::Trial,
    ]))->toThrow(QueryException::class);
});

it('requires both a plan feature and a user permission', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($organization)->for($plan)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $user,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Waiter->value),
    );

    $session = ['current_organization_id' => $organization->id];

    $this->actingAs($user)
        ->withSession($session)
        ->get('/_test/feature/pos')
        ->assertForbidden();

    $feature = Feature::factory()->create(['code' => 'pos']);
    $plan->features()->attach($feature);
    $subscription->refresh();

    $this->actingAs($user)
        ->withSession($session)
        ->get('/_test/feature/pos')
        ->assertNoContent();
});

it('enforces the plan store limit', function () {
    $organization = Organization::factory()->create();
    $plan = Plan::factory()->create(['max_stores' => 1]);
    Subscription::factory()->for($organization)->for($plan)->create();
    Store::factory()->for($organization)->create();

    expect(fn () => app(PlanLimits::class)->ensureCanAddStore($organization))
        ->toThrow(ValidationException::class);
});

it('enforces the plan user limit', function () {
    $organization = Organization::factory()->create();
    $plan = Plan::factory()->create(['max_users' => 1]);
    Subscription::factory()->for($organization)->for($plan)->create();
    $organization->users()->attach(User::factory()->create());

    expect(fn () => app(PlanLimits::class)->ensureCanAddUser($organization))
        ->toThrow(ValidationException::class);
});

it('allows the platform panel to manage plans features and subscriptions', function () {
    $platformUser = User::factory()->platformAdmin()->create();

    $this->actingAs($platformUser)->get('/platform/plans')->assertOk();
    $this->actingAs($platformUser)->get('/platform/features')->assertOk();
    $this->actingAs($platformUser)->get('/platform/subscriptions')->assertOk();
    $this->actingAs($platformUser)->get('/platform/subscriptions/create')->assertOk();
});

<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\CreateOrganizationData;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function onboardingData(Plan $plan, User $owner, array $overrides = []): CreateOrganizationData
{
    return new CreateOrganizationData(...array_merge([
        'name' => 'Burger House',
        'slug' => 'burger-house',
        'phone' => '+998901234567',
        'storeName' => 'Chilonzor',
        'storeAddress' => 'Tashkent',
        'storePhone' => '+998901234568',
        'timezone' => 'Asia/Tashkent',
        'planId' => $plan->id,
        'ownerId' => $owner->id,
        'startsAt' => CarbonImmutable::now()->subMinute(),
        'endsAt' => CarbonImmutable::now()->addMonth(),
    ], $overrides));
}

it('provisions a usable organization in one transaction', function () {
    $plan = Plan::factory()->create(['max_stores' => 1, 'max_users' => 1]);
    $owner = User::factory()->create();

    $organization = app(CreateOrganization::class)->execute(onboardingData($plan, $owner));

    expect($organization->stores)->toHaveCount(1)
        ->and($organization->users->modelKeys())->toBe([$owner->id])
        ->and($organization->roles)->toHaveCount(4)
        ->and($organization->subscriptions)->toHaveCount(1)
        ->and($organization->subscriptions->first()->status)->toBe(SubscriptionStatus::Active)
        ->and($organization->stores->first()->users()->whereKey($owner->id)->exists())->toBeTrue()
        ->and(app(OrganizationAuthorization::class)->runForUserInTenant(
            $owner,
            $organization,
            fn (User $user): bool => $user->hasRole(OrganizationRole::Owner->value),
        ))->toBeTrue();
});

it('rolls back every onboarding write when provisioning fails', function () {
    $plan = Plan::factory()->create();
    $owner = User::factory()->create();
    $assignOwner = Mockery::mock(AssignOrganizationOwner::class);
    $assignOwner->shouldReceive('execute')->once()->andThrow(new RuntimeException('Provisioning failed'));
    app()->instance(AssignOrganizationOwner::class, $assignOwner);

    expect(fn () => app(CreateOrganization::class)->execute(onboardingData($plan, $owner)))
        ->toThrow(RuntimeException::class);

    expect(Organization::query()->where('slug', 'burger-house')->exists())->toBeFalse()
        ->and(Store::query()->exists())->toBeFalse()
        ->and(Subscription::query()->exists())->toBeFalse();
});

it('rejects a plan that cannot provision the required foundation', function () {
    $plan = Plan::factory()->create(['max_stores' => 0, 'max_users' => 0]);
    $owner = User::factory()->create();

    expect(fn () => app(CreateOrganization::class)->execute(onboardingData($plan, $owner)))
        ->toThrow(ValidationException::class);
});

it('keeps onboarding roles and membership isolated from another tenant', function () {
    $plan = Plan::factory()->create();
    $owner = User::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $organization = app(CreateOrganization::class)->execute(onboardingData($plan, $owner));

    expect($organization->roles()->where('organization_id', $otherOrganization->id)->exists())->toBeFalse()
        ->and($otherOrganization->users()->whereKey($owner->id)->exists())->toBeFalse();
});

it('loads the platform onboarding form', function () {
    $platformUser = User::factory()->create();

    $this->actingAs($platformUser)
        ->get('/platform/onboard-organization')
        ->assertOk()
        ->assertSee('Create organization');
});

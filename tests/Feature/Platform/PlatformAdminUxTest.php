<?php

use App\Actions\Subscriptions\ManageSubscription;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

it('keeps platform authorization separate from organization membership', function () {
    $organizationUser = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($organizationUser);

    $this->actingAs($organizationUser)->get('/platform')->assertForbidden();
    $this->actingAs($organizationUser)->get('/platform/organizations')->assertForbidden();

    $platformAdmin = User::factory()->platformAdmin()->create();
    $this->actingAs($platformAdmin)->get('/platform')->assertOk();
    $this->actingAs($platformAdmin)->get('/platform/organizations')->assertOk();
    $this->actingAs($platformAdmin)->get('/platform/platform-users')->assertOk();
    $this->actingAs($platformAdmin)->get('/platform/platform-users/create')->assertOk();
    $this->actingAs($platformAdmin)->get('/platform/system-settings')->assertOk();
});

it('activates an expired subscription and gives it a future end date', function () {
    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Expired,
        'starts_at' => now()->subMonths(2),
        'ends_at' => now()->subMonth(),
    ]);

    $managed = app(ManageSubscription::class)->activate($subscription);

    expect($managed->status)->toBe(SubscriptionStatus::Active)
        ->and($managed->starts_at->isPast())->toBeTrue()
        ->and($managed->ends_at->isFuture())->toBeTrue();
});

it('extends a subscription from its current future expiry', function () {
    $originalEnd = now()->addDays(10)->startOfSecond();
    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Trial,
        'ends_at' => $originalEnd,
    ]);

    $managed = app(ManageSubscription::class)->extend($subscription, 30);

    expect($managed->status)->toBe(SubscriptionStatus::Active)
        ->and($managed->ends_at->equalTo($originalEnd->addDays(30)))->toBeTrue();
});

it('shows organization status and support counts to platform administrators', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create(['name' => 'Support Cafe']);
    $plan = Plan::factory()->create(['name' => 'Support Plan']);
    Subscription::factory()->for($organization)->for($plan)->create();

    $this->actingAs($platformAdmin)
        ->get('/platform/organizations')
        ->assertOk()
        ->assertSee('Support Cafe')
        ->assertSee('Support Plan');

    $this->actingAs($platformAdmin)
        ->get("/platform/organizations/{$organization->id}/edit")
        ->assertOk();
});

<?php

namespace App\Domain\Subscription;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class PlanLimits
{
    public function __construct(private readonly SubscriptionAccess $subscriptions) {}

    public function ensureCanAddStore(Organization $organization): void
    {
        $subscription = $this->subscriptions->activeSubscription($organization)
            ?? throw ValidationException::withMessages(['subscription' => 'An active subscription is required.']);

        if ($organization->stores()->count() >= $subscription->plan->max_stores) {
            throw ValidationException::withMessages(['store' => 'The plan store limit has been reached.']);
        }
    }

    public function ensureCanAddUser(Organization $organization): void
    {
        $subscription = $this->subscriptions->activeSubscription($organization)
            ?? throw ValidationException::withMessages(['subscription' => 'An active subscription is required.']);

        if ($organization->users()->count() >= $subscription->plan->max_users) {
            throw ValidationException::withMessages(['user' => 'The plan user limit has been reached.']);
        }
    }
}

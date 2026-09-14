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
            ?? throw ValidationException::withMessages(['subscription' => 'Faol obuna talab qilinadi.']);

        if ($organization->stores()->count() >= $subscription->plan->max_stores) {
            throw ValidationException::withMessages(['store' => 'Tarifdagi filiallar limiti tugagan.']);
        }
    }

    public function ensureCanAddUser(Organization $organization): void
    {
        $subscription = $this->subscriptions->activeSubscription($organization)
            ?? throw ValidationException::withMessages(['subscription' => 'Faol obuna talab qilinadi.']);

        if ($organization->users()->count() >= $subscription->plan->max_users) {
            throw ValidationException::withMessages(['user' => 'Tarifdagi foydalanuvchilar limiti tugagan.']);
        }
    }
}

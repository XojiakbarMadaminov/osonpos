<?php

namespace App\Domain\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;

class SubscriptionAccess
{
    public function activeSubscription(Organization $organization): ?Subscription
    {
        return $organization->subscriptions()
            ->with('plan.features')
            ->whereIn('status', [SubscriptionStatus::Trial, SubscriptionStatus::Active])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    public function isActive(Organization $organization): bool
    {
        return $this->activeSubscription($organization)?->isActive() === true;
    }

    public function hasFeature(Organization $organization, string $featureCode): bool
    {
        return $this->activeSubscription($organization)?->plan->features
            ->contains('code', $featureCode) === true;
    }
}

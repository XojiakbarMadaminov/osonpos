<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManageSubscription
{
    public function activate(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'starts_at' => min($subscription->starts_at, now()),
                'ends_at' => $subscription->ends_at->isFuture() ? $subscription->ends_at : now()->addMonth(),
            ]);

            return $subscription->refresh();
        });
    }

    public function extend(Subscription $subscription, int $days = 30): Subscription
    {
        return DB::transaction(function () use ($subscription, $days): Subscription {
            $base = $subscription->ends_at->isFuture()
                ? CarbonImmutable::instance($subscription->ends_at)
                : CarbonImmutable::now();

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $base->addDays($days),
            ]);

            return $subscription->refresh();
        });
    }
}

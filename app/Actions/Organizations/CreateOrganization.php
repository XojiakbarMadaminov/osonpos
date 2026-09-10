<?php

namespace App\Actions\Organizations;

use App\Domain\Subscription\PlanLimits;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrganization
{
    public function __construct(
        private readonly AssignOrganizationOwner $assignOwner,
        private readonly PlanLimits $planLimits,
    ) {}

    public function execute(CreateOrganizationData $data): Organization
    {
        $plan = Plan::query()->whereKey($data->planId)->where('is_active', true)->firstOrFail();
        $owner = User::query()->findOrFail($data->ownerId);

        if ($plan->max_stores < 1 || $plan->max_users < 1) {
            throw ValidationException::withMessages([
                'plan_id' => 'The selected plan must allow at least one store and one user.',
            ]);
        }

        if ($data->endsAt->lessThanOrEqualTo($data->startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'The subscription end must follow its start.']);
        }

        return DB::transaction(function () use ($data, $plan, $owner): Organization {
            $organization = Organization::query()->create([
                'name' => $data->name,
                'slug' => $data->slug,
                'phone' => $data->phone,
                'status' => 'ACTIVE',
            ]);

            $organization->subscriptions()->create([
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Active,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
            ]);

            $this->planLimits->ensureCanAddStore($organization);
            $store = $organization->stores()->create([
                'name' => $data->storeName,
                'address' => $data->storeAddress,
                'phone' => $data->storePhone,
                'timezone' => $data->timezone,
                'is_active' => true,
            ]);

            $this->planLimits->ensureCanAddUser($organization);
            $this->assignOwner->execute($organization, $owner);
            $store->users()->syncWithoutDetaching([$owner->getKey()]);

            return $organization->load(['stores', 'subscriptions.plan', 'users', 'roles']);
        });
    }
}

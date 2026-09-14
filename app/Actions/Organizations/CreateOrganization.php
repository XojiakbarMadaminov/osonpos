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
        if ($plan->max_stores < 1 || $plan->max_users < 1) {
            throw ValidationException::withMessages([
                'plan_id' => 'Tanlangan tarif kamida bitta filial va bitta foydalanuvchiga ruxsat berishi kerak.',
            ]);
        }

        if ($data->endsAt->lessThanOrEqualTo($data->startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'Obunaning tugash vaqti boshlanish vaqtidan keyin bo‘lishi kerak.']);
        }

        return DB::transaction(function () use ($data, $plan): Organization {
            $owner = $data->ownerId
                ? User::query()->findOrFail($data->ownerId)
                : User::query()->create([
                    'name' => $data->ownerName,
                    'email' => strtolower((string) $data->ownerEmail),
                    'password' => $data->ownerPassword,
                ]);

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

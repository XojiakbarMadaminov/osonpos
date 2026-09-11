<?php

namespace App\Actions\Organizations;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Authorization\StoreAccess;
use App\Domain\Subscription\PlanLimits;
use App\Enums\OrganizationRole;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SaveOrganizationUser
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationAuthorization $authorization,
        private readonly StoreAccess $storeAccess,
        private readonly PlanLimits $planLimits,
    ) {}

    public function execute(User $actor, ?User $member, array $attributes, int $roleId, array $storeIds): User
    {
        $organization = $this->tenantContext->requireCurrent();

        if ($member && ! $member->organizations()->whereKey($organization)->exists()) {
            throw ValidationException::withMessages(['user' => 'The user does not belong to this organization.']);
        }

        $allowedStoreIds = $this->storeAccess->accessibleStoreIds($actor)->map(fn ($id): int => (int) $id);
        $requestedStoreIds = collect($storeIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($requestedStoreIds->diff($allowedStoreIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['store_ids' => 'A selected store is not accessible.']);
        }

        $role = Role::query()
            ->whereKey($roleId)
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        $actorIsOwner = $this->authorization->runForUserInTenant(
            $actor,
            $organization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );

        if (! $actorIsOwner && ! in_array($role->name, [OrganizationRole::Cashier->value, OrganizationRole::Waiter->value], true)) {
            throw ValidationException::withMessages(['role_id' => 'Only an owner may assign privileged roles.']);
        }

        return DB::transaction(function () use ($organization, $member, $attributes, $requestedStoreIds, $role): User {
            if (! $member) {
                $this->planLimits->ensureCanAddUser($organization);
                $member = User::query()->create($attributes);
                $organization->users()->attach($member);
            } else {
                $member->update($attributes);
            }

            $organizationStoreIds = $organization->stores()->pluck('id');
            $member->stores()->detach($organizationStoreIds);
            $member->stores()->attach($requestedStoreIds);

            $this->authorization->runForUserInTenant(
                $member,
                $organization,
                fn (User $tenantUser) => $tenantUser->syncRoles([$role]),
            );

            return $member->refresh();
        });
    }
}

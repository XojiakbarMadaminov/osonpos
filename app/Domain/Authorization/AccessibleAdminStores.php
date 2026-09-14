<?php

namespace App\Domain\Authorization;

use App\Enums\OrganizationRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

class AccessibleAdminStores
{
    public function __construct(
        private readonly OrganizationAuthorization $authorization,
    ) {}

    /** @return Collection<int, Store> */
    public function forUser(User $user): Collection
    {
        $organizations = $user->organizations()->orderBy('name')->get();
        $organizationIds = $organizations->modelKeys();
        $ownerOrganizationIds = $organizations
            ->filter(fn ($organization): bool => $this->authorization->runForUserInTenant(
                $user,
                $organization,
                fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
            ))
            ->modelKeys();

        return Store::query()
            ->with('organization')
            ->whereIn('organization_id', $organizationIds)
            ->where('is_active', true)
            ->where(function ($query) use ($ownerOrganizationIds, $user): void {
                $query
                    ->whereIn('organization_id', $ownerOrganizationIds)
                    ->orWhereHas('users', fn ($users) => $users->whereKey($user->getKey()));
            })
            ->get()
            ->sortBy(fn (Store $store): string => $store->organization->name."\0".$store->name)
            ->values();
    }
}

<?php

namespace App\Domain\Authorization;

use App\Enums\OrganizationRole;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Collection;

class StoreAccess
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrganizationAuthorization $authorization,
    ) {}

    public function allows(User $user, Store $store): bool
    {
        $organization = $this->tenantContext->current();

        if (! $organization || ! $store->belongsToTenant($organization)) {
            return false;
        }

        $isOwner = $this->authorization->runForUserInTenant(
            $user,
            $organization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );

        return $isOwner || $user->stores()->whereKey($store->getKey())->exists();
    }

    public function accessibleStoreIds(User $user): Collection
    {
        $organization = $this->tenantContext->requireCurrent();
        $isOwner = $this->authorization->runForUserInTenant(
            $user,
            $organization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );

        return $isOwner
            ? $organization->stores()->pluck('id')
            : $user->stores()->where('stores.organization_id', $organization->getKey())->pluck('stores.id');
    }
}

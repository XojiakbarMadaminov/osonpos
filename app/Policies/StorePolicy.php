<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class StorePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::StoresView);
    }

    public function view(User $user, Store $store): bool
    {
        return $this->allows($user, OrganizationPermission::StoresView, $store)
            && app(StoreAccess::class)->allows($user, $store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::StoresManage);
    }

    public function update(User $user, Store $store): bool
    {
        return $this->allows($user, OrganizationPermission::StoresManage, $store)
            && app(StoreAccess::class)->allows($user, $store);
    }
}

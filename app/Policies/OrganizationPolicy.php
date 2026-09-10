<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;
use App\Support\TenantContext;

class OrganizationPolicy
{
    use AuthorizesTenantResources;

    public function view(User $user, Organization $organization): bool
    {
        return $this->allows($user, OrganizationPermission::StoresView)
            && app(TenantContext::class)->id() === $organization->getKey();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->allows($user, OrganizationPermission::StoresManage)
            && app(TenantContext::class)->id() === $organization->getKey();
    }
}

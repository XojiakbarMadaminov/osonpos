<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;
use App\Support\TenantContext;

class UserPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::UsersView);
    }

    public function view(User $user, User $member): bool
    {
        return $this->allows($user, OrganizationPermission::UsersView)
            && $member->organizations()->whereKey(app(TenantContext::class)->id())->exists();
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::UsersManage);
    }

    public function update(User $user, User $member): bool
    {
        return $this->allows($user, OrganizationPermission::UsersManage)
            && $member->organizations()->whereKey(app(TenantContext::class)->id())->exists();
    }

    public function delete(User $user, User $member): bool
    {
        return false;
    }
}

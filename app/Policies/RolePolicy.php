<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allows($user, OrganizationPermission::RolesView, $role);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::RolesManage);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allows($user, OrganizationPermission::RolesManage, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allows($user, OrganizationPermission::RolesManage, $role);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::RolesManage);
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Role $role): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Shift;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class ShiftPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ShiftsView);
    }

    public function view(User $user, Shift $shift): bool
    {
        return $this->allows($user, OrganizationPermission::ShiftsView, $shift)
            && app(StoreAccess::class)->allows($user, $shift->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ShiftsManage);
    }

    public function update(User $user, Shift $shift): bool
    {
        return $this->allows($user, OrganizationPermission::ShiftsManage, $shift)
            && app(StoreAccess::class)->allows($user, $shift->store);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return false;
    }
}

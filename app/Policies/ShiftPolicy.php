<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
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
        if (! $this->allows($user, OrganizationPermission::ShiftsView, $shift)
            || ! app(StoreAccess::class)->allows($user, $shift->store)) {
            return false;
        }

        return $shift->user_id === $user->getKey()
            || $user->hasAnyRole([OrganizationRole::Owner->value, OrganizationRole::Manager->value]);
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

    public function closeAsSupervisor(User $user, Shift $shift): bool
    {
        return $this->update($user, $shift)
            && $user->hasAnyRole([OrganizationRole::Owner->value, OrganizationRole::Manager->value]);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return false;
    }
}

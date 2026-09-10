<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Device;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class DevicePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersView);
    }

    public function view(User $user, Device $device): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersView, $device)
            && app(StoreAccess::class)->allows($user, $device->store);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Device $device): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersManage, $device)
            && app(StoreAccess::class)->allows($user, $device->store);
    }

    public function delete(User $user, Device $device): bool
    {
        return false;
    }
}

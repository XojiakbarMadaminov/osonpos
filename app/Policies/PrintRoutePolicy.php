<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\PrintRoute;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class PrintRoutePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersView);
    }

    public function view(User $user, PrintRoute $route): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersView, $route)
            && app(StoreAccess::class)->allows($user, $route->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersManage);
    }

    public function update(User $user, PrintRoute $route): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersManage, $route)
            && app(StoreAccess::class)->allows($user, $route->store);
    }

    public function delete(User $user, PrintRoute $route): bool
    {
        return false;
    }
}

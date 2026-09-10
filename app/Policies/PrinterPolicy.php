<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Printer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class PrinterPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersView);
    }

    public function view(User $user, Printer $printer): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersView, $printer)
            && app(StoreAccess::class)->allows($user, $printer->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersManage);
    }

    public function update(User $user, Printer $printer): bool
    {
        return $this->allows($user, OrganizationPermission::PrintersManage, $printer)
            && app(StoreAccess::class)->allows($user, $printer->store);
    }

    public function delete(User $user, Printer $printer): bool
    {
        return false;
    }
}

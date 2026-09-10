<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Table;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class TablePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::TablesView);
    }

    public function view(User $user, Table $table): bool
    {
        return $this->allows($user, OrganizationPermission::TablesView, $table)
            && app(StoreAccess::class)->allows($user, $table->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::TablesManage);
    }

    public function update(User $user, Table $table): bool
    {
        return $this->allows($user, OrganizationPermission::TablesManage, $table)
            && app(StoreAccess::class)->allows($user, $table->store);
    }

    public function delete(User $user, Table $table): bool
    {
        return false;
    }
}

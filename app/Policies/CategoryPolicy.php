<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class CategoryPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsView);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsView, $category);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsManage);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsManage, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return false;
    }
}

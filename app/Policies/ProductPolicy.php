<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class ProductPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsView);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsView, $product);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsManage);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, OrganizationPermission::ProductsManage, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return false;
    }
}

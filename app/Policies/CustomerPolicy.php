<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class CustomerPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersView, $customer);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersCreate);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersUpdate, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}

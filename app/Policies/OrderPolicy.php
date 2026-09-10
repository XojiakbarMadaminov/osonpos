<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class OrderPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersView);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersView, $order)
            && app(StoreAccess::class)->allows($user, $order->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersCreate);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersUpdate, $order)
            && app(StoreAccess::class)->allows($user, $order->store);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->allows($user, OrganizationPermission::OrdersCancel, $order)
            && app(StoreAccess::class)->allows($user, $order->store);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationPermission;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class PaymentPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PaymentsView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->allows($user, OrganizationPermission::PaymentsView, $payment)
            && app(StoreAccess::class)->allows($user, $payment->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::PaymentsCreate);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}

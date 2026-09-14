<?php

namespace App\Policies;

use App\Domain\Authorization\StoreAccess;
use App\Enums\ExpenseStatus;
use App\Enums\OrganizationPermission;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class ExpensePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ExpensesView);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->allows($user, OrganizationPermission::ExpensesView, $expense)
            && app(StoreAccess::class)->allows($user, $expense->store);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, OrganizationPermission::ExpensesManage);
    }

    public function cancel(User $user, Expense $expense): bool
    {
        return $expense->status === ExpenseStatus::Active
            && $this->allows($user, OrganizationPermission::ExpensesManage, $expense)
            && app(StoreAccess::class)->allows($user, $expense->store);
    }

    public function update(User $user, Expense $expense): bool
    {
        return false;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return false;
    }
}

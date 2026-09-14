<?php

namespace App\Actions\Expenses;

use App\Domain\Authorization\StoreAccess;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelExpense
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreAccess $storeAccess,
    ) {}

    public function execute(Expense $expense, User $user, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $user, $reason): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());
            $reason = trim($reason);

            if (! $expense->belongsToTenant($this->tenantContext->requireCurrent())
                || ! $this->storeAccess->allows($user, $expense->store)) {
                abort(403);
            }

            if ($expense->status !== ExpenseStatus::Active) {
                throw ValidationException::withMessages(['expense' => 'Faqat faol chiqimni bekor qilish mumkin.']);
            }

            if ($reason === '') {
                throw ValidationException::withMessages(['cancellation_reason' => 'Bekor qilish sababini kiriting.']);
            }

            $expense->canceller()->associate($user);
            $expense->forceFill([
                'status' => ExpenseStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $expense;
        });
    }
}

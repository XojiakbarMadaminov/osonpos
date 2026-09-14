<?php

namespace App\Actions\Expenses;

use App\Domain\Authorization\StoreAccess;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExpense
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreAccess $storeAccess,
    ) {}

    public function execute(
        User $user,
        int $storeId,
        ExpenseType $type,
        int $amount,
        ?string $description,
        CarbonImmutable $incurredOn,
    ): Expense {
        return DB::transaction(function () use ($user, $storeId, $type, $amount, $description, $incurredOn): Expense {
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Summa noldan katta bo‘lishi kerak.']);
            }

            $description = trim((string) $description);
            $description = $description === '' ? null : $description;

            $organization = $this->tenantContext->requireCurrent();
            $store = Store::query()->forTenant($organization)->findOrFail($storeId);
            abort_unless($this->storeAccess->allows($user, $store), 403);

            $expense = new Expense([
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'incurred_on' => $incurredOn,
            ]);
            $expense->organization()->associate($organization);
            $expense->store()->associate($store);
            $expense->creator()->associate($user);
            $expense->forceFill(['status' => ExpenseStatus::Active])->save();

            return $expense;
        });
    }
}

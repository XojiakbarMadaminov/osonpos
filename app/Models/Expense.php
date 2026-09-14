<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['type', 'amount', 'description', 'incurred_on'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Expense $expense): void {
            $storeIsValid = Store::query()
                ->whereKey($expense->store_id)
                ->where('organization_id', $expense->organization_id)
                ->exists();

            if (! $storeIsValid) {
                throw ValidationException::withMessages([
                    'store_id' => 'Filial joriy tashkilotga tegishli bo‘lishi kerak.',
                ]);
            }
        });

        static::deleting(function (): never {
            throw ValidationException::withMessages([
                'expense' => 'Chiqim moliyaviy tarixdan o‘chirilmaydi.',
            ]);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ExpenseStatus::Active);
    }

    protected function casts(): array
    {
        return [
            'type' => ExpenseType::class,
            'amount' => 'integer',
            'incurred_on' => 'immutable_date',
            'status' => ExpenseStatus::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}

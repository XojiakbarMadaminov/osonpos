<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['method', 'amount'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $orderIsValid = Order::query()
                ->whereKey($payment->order_id)
                ->where('organization_id', $payment->organization_id)
                ->where('store_id', $payment->store_id)
                ->exists();
            $deviceIsValid = $payment->device_id === null || Device::query()
                ->whereKey($payment->device_id)
                ->where('organization_id', $payment->organization_id)
                ->where('store_id', $payment->store_id)
                ->exists();
            $shiftIsValid = $payment->shift_id === null || Shift::query()
                ->whereKey($payment->shift_id)
                ->where('organization_id', $payment->organization_id)
                ->where('store_id', $payment->store_id)
                ->where('device_id', $payment->device_id)
                ->where('user_id', $payment->created_by)
                ->exists();

            if (! $orderIsValid || ! $deviceIsValid || ! $shiftIsValid) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment resources must belong to the same organization and store.',
                ]);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
        ];
    }
}

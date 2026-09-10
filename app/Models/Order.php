<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable(['type', 'table_id', 'customer_id', 'note'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $storeIsValid = Store::query()
                ->whereKey($order->store_id)
                ->where('organization_id', $order->organization_id)
                ->exists();
            $tableIsValid = $order->table_id === null || Table::query()
                ->whereKey($order->table_id)
                ->where('organization_id', $order->organization_id)
                ->where('store_id', $order->store_id)
                ->exists();
            $customerIsValid = $order->customer_id === null || Customer::query()
                ->whereKey($order->customer_id)
                ->where('organization_id', $order->organization_id)
                ->exists();
            $deviceIsValid = $order->device_id === null || Device::query()
                ->whereKey($order->device_id)
                ->where('organization_id', $order->organization_id)
                ->where('store_id', $order->store_id)
                ->exists();

            if (! $storeIsValid || ! $tableIsValid || ! $customerIsValid || ! $deviceIsValid) {
                throw ValidationException::withMessages([
                    'order' => 'Order resources must belong to the same organization and store.',
                ]);
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryDetail(): HasOne
    {
        return $this->hasOne(DeliveryDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}

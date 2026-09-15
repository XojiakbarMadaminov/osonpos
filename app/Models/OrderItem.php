<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['note'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $orderIsValid = Order::query()
                ->whereKey($item->order_id)
                ->where('organization_id', $item->organization_id)
                ->where('store_id', $item->store_id)
                ->exists();
            $productIsValid = $item->product_id === null || Product::query()
                ->whereKey($item->product_id)
                ->where('organization_id', $item->organization_id)
                ->where('store_id', $item->store_id)
                ->exists();

            if (! $orderIsValid || ! $productIsValid) {
                throw ValidationException::withMessages([
                    'item' => 'Buyurtma mahsuloti bir xil tashkilot va filialga tegishli bo‘lishi kerak.',
                ]);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function removals(): HasMany
    {
        return $this->hasMany(OrderItemRemoval::class);
    }

    public function removedQuantity(): int
    {
        if (isset($this->attributes['removals_sum_quantity'])) {
            return (int) $this->attributes['removals_sum_quantity'];
        }

        if ($this->relationLoaded('removals')) {
            return (int) $this->removals->sum('quantity');
        }

        if (! $this->exists) {
            return 0;
        }

        return (int) $this->removals()->sum('quantity');
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->removedQuantity());
    }

    public function remainingTotal(): int
    {
        return $this->remainingQuantity() * $this->unit_price;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'total' => 'integer',
            'kitchen_printed_at' => 'immutable_datetime',
        ];
    }
}

<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\OrderItemRemovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([])]
class OrderItemRemoval extends Model
{
    /** @use HasFactory<OrderItemRemovalFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (OrderItemRemoval $removal): void {
            $itemIsValid = OrderItem::query()
                ->whereKey($removal->order_item_id)
                ->where('order_id', $removal->order_id)
                ->where('organization_id', $removal->organization_id)
                ->where('store_id', $removal->store_id)
                ->exists();

            if (! $itemIsValid) {
                throw ValidationException::withMessages([
                    'item' => 'Ayirilayotgan mahsulot shu buyurtma va filialga tegishli bo‘lishi kerak.',
                ]);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'total' => 'integer',
            'kitchen_print_required' => 'boolean',
            'kitchen_printed_at' => 'immutable_datetime',
        ];
    }
}

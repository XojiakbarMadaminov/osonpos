<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\DeliveryDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['order_id', 'address', 'delivery_fee', 'note'])]
class DeliveryDetail extends Model
{
    /** @use HasFactory<DeliveryDetailFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (DeliveryDetail $detail): void {
            $storeIsValid = Store::query()
                ->whereKey($detail->store_id)
                ->where('organization_id', $detail->organization_id)
                ->exists();

            if (! $storeIsValid) {
                throw ValidationException::withMessages([
                    'store_id' => 'The store must belong to the delivery organization.',
                ]);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

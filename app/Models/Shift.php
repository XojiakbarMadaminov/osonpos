<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use App\Enums\ShiftStatus;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['opening_cash'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Shift $shift): void {
            $deviceIsValid = Device::query()
                ->whereKey($shift->device_id)
                ->where('organization_id', $shift->organization_id)
                ->where('store_id', $shift->store_id)
                ->exists();

            if (! $deviceIsValid) {
                throw ValidationException::withMessages([
                    'shift' => 'The shift device must belong to its organization and store.',
                ]);
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opening_cash' => 'integer',
            'closing_cash' => 'integer',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}

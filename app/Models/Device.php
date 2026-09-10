<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'code', 'is_active', 'last_seen_at'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Device $device): void {
            $storeBelongsToTenant = Store::query()
                ->whereKey($device->store_id)
                ->where('organization_id', $device->organization_id)
                ->exists();

            if (! $storeBelongsToTenant) {
                throw ValidationException::withMessages([
                    'store_id' => 'The store must belong to the device organization.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}

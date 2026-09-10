<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Pos\PosConfigurationRepository;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\PrinterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'paper_width', 'is_active'])]
class Printer extends Model
{
    /** @use HasFactory<PrinterFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Printer $printer): void {
            $storeIsValid = Store::query()
                ->whereKey($printer->store_id)
                ->where('organization_id', $printer->organization_id)
                ->exists();
            $deviceIsValid = $printer->device_id === null || Device::query()
                ->whereKey($printer->device_id)
                ->where('organization_id', $printer->organization_id)
                ->where('store_id', $printer->store_id)
                ->exists();

            if (! $storeIsValid || ! $deviceIsValid) {
                throw ValidationException::withMessages([
                    'store_id' => 'The printer store and device must belong to its organization.',
                ]);
            }
        });
        static::saved(function (Printer $printer): void {
            app(PosConfigurationRepository::class)->forget($printer->organization_id, $printer->store_id);

            if ($printer->wasChanged('store_id')) {
                app(PosConfigurationRepository::class)->forget($printer->organization_id, (int) $printer->getOriginal('store_id'));
            }
        });
        static::deleted(fn (Printer $printer) => app(PosConfigurationRepository::class)->forget($printer->organization_id, $printer->store_id));
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function printRoutes(): HasMany
    {
        return $this->hasMany(PrintRoute::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

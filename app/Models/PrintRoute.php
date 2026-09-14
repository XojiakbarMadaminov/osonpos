<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Pos\PosConfigurationRepository;
use App\Domain\Store\Concerns\BelongsToStore;
use App\Enums\PrintType;
use Database\Factories\PrintRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['print_type'])]
class PrintRoute extends Model
{
    /** @use HasFactory<PrintRouteFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (PrintRoute $route): void {
            $printerIsValid = Printer::query()
                ->whereKey($route->printer_id)
                ->where('organization_id', $route->organization_id)
                ->where('store_id', $route->store_id)
                ->exists();

            if (! $printerIsValid) {
                throw ValidationException::withMessages([
                    'printer_id' => 'Printer chop etish yo‘nalishidagi filialga tegishli bo‘lishi kerak.',
                ]);
            }
        });
        static::saved(function (PrintRoute $route): void {
            app(PosConfigurationRepository::class)->forget($route->organization_id, $route->store_id);

            if ($route->wasChanged('store_id')) {
                app(PosConfigurationRepository::class)->forget($route->organization_id, (int) $route->getOriginal('store_id'));
            }
        });
        static::deleted(fn (PrintRoute $route) => app(PosConfigurationRepository::class)->forget($route->organization_id, $route->store_id));
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    protected function casts(): array
    {
        return ['print_type' => PrintType::class];
    }
}

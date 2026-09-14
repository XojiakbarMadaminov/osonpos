<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Pos\PosConfigurationRepository;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\TableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'number', 'capacity', 'is_active'])]
class Table extends Model
{
    /** @use HasFactory<TableFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Table $table): void {
            $storeBelongsToTenant = Store::query()
                ->whereKey($table->store_id)
                ->where('organization_id', $table->organization_id)
                ->exists();

            if (! $storeBelongsToTenant) {
                throw ValidationException::withMessages([
                    'store_id' => 'Filial stol tashkilotiga tegishli bo‘lishi kerak.',
                ]);
            }
        });
        static::saved(function (Table $table): void {
            app(PosConfigurationRepository::class)->forget($table->organization_id, $table->store_id);

            if ($table->wasChanged('store_id')) {
                app(PosConfigurationRepository::class)->forget($table->organization_id, (int) $table->getOriginal('store_id'));
            }
        });
        static::deleted(fn (Table $table) => app(PosConfigurationRepository::class)->forget($table->organization_id, $table->store_id));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

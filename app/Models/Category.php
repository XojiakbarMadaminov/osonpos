<?php

namespace App\Models;

use App\Domain\Catalog\CatalogRepository;
use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToStore, BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $storeBelongsToTenant = Store::query()
                ->whereKey($category->store_id)
                ->where('organization_id', $category->organization_id)
                ->exists();

            if (! $storeBelongsToTenant) {
                throw ValidationException::withMessages([
                    'store_id' => 'Filial kategoriya tashkilotiga tegishli bo‘lishi kerak.',
                ]);
            }
        });
        static::saved(function (Category $category): void {
            app(CatalogRepository::class)->forget($category->organization_id, $category->store_id);

            if ($category->wasChanged('store_id')) {
                app(CatalogRepository::class)->forget($category->organization_id, (int) $category->getOriginal('store_id'));
            }
        });
        static::deleted(fn (Category $category) => app(CatalogRepository::class)->forget($category->organization_id, $category->store_id));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

<?php

namespace App\Models;

use App\Domain\Catalog\CatalogRepository;
use App\Domain\Organization\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['category_id', 'name', 'price', 'is_active', 'sort_order'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $categoryBelongsToTenant = Category::query()
                ->whereKey($product->category_id)
                ->where('organization_id', $product->organization_id)
                ->exists();

            if (! $categoryBelongsToTenant) {
                throw ValidationException::withMessages([
                    'category_id' => 'The category must belong to the product organization.',
                ]);
            }
        });
        static::saved(fn (Product $product) => app(CatalogRepository::class)->forget($product->organization_id));
        static::deleted(fn (Product $product) => app(CatalogRepository::class)->forget($product->organization_id));
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

<?php

namespace App\Models;

use App\Domain\Catalog\CatalogRepository;
use App\Domain\Organization\Concerns\BelongsToTenant;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saved(fn (Category $category) => app(CatalogRepository::class)->forget($category->organization_id));
        static::deleted(fn (Category $category) => app(CatalogRepository::class)->forget($category->organization_id));
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

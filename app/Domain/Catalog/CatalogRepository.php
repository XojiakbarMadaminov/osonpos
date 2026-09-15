<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;

class CatalogRepository
{
    public function forStore(Organization|int $organization, Store|int $store): array
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;
        $storeId = $store instanceof Store ? $store->getKey() : $store;

        return Cache::remember($this->cacheKey($organizationId, $storeId), now()->addHour(), function () use ($organizationId, $storeId): array {
            return Category::query()
                ->forTenant($organizationId)
                ->forStore($storeId)
                ->where('is_active', true)
                ->with(['products' => fn ($query) => $query
                    ->select(['id', 'category_id', 'name', 'price', 'sort_order'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    public function forget(Organization|int $organization, Store|int $store): void
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;
        $storeId = $store instanceof Store ? $store->getKey() : $store;

        Cache::forget($this->cacheKey($organizationId, $storeId));
    }

    private function cacheKey(int $organizationId, int $storeId): string
    {
        return "pos:catalog:organization:{$organizationId}:store:{$storeId}";
    }
}

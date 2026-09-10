<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;

class CatalogRepository
{
    public function forOrganization(Organization|int $organization): array
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return Cache::remember($this->cacheKey($organizationId), now()->addHour(), function () use ($organizationId): array {
            return Category::query()
                ->forTenant($organizationId)
                ->where('is_active', true)
                ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    public function forget(Organization|int $organization): void
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        Cache::forget($this->cacheKey($organizationId));
    }

    private function cacheKey(int $organizationId): string
    {
        return "pos:catalog:organization:{$organizationId}";
    }
}

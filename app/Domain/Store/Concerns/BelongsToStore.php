<?php

namespace App\Domain\Store\Concerns;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeForStore(Builder $query, Store|int $store): Builder
    {
        return $query->where(
            $this->qualifyColumn('store_id'),
            $store instanceof Store ? $store->getKey() : $store,
        );
    }

    public function belongsToStore(Store|int $store): bool
    {
        $storeId = $store instanceof Store ? $store->getKey() : $store;

        return (int) $this->getAttribute('store_id') === (int) $storeId;
    }
}

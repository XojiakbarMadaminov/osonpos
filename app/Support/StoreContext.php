<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class StoreContext
{
    private ?Store $store = null;

    public function resolveFor(User $user, TenantContext $tenantContext, int|string|null $preferredStoreId = null): Store
    {
        $query = $user->stores()
            ->where('stores.organization_id', $tenantContext->requireCurrent()->getKey())
            ->where('stores.is_active', true)
            ->orderBy('stores.id');

        $store = $preferredStoreId === null
            ? $query->first()
            : $query->whereKey($preferredStoreId)->first();

        if (! $store) {
            throw new AuthorizationException('No accessible store could be resolved.');
        }

        return $this->store = $store;
    }

    public function current(): ?Store
    {
        return $this->store;
    }

    public function requireCurrent(): Store
    {
        return $this->store
            ?? throw new LogicException('Store context has not been initialized.');
    }

    public function id(): ?int
    {
        return $this->store?->getKey();
    }

    public function owns(object $resource): bool
    {
        return $this->store !== null
            && isset($resource->store_id)
            && (int) $resource->store_id === (int) $this->store->getKey();
    }

    public function clear(): void
    {
        $this->store = null;
    }
}

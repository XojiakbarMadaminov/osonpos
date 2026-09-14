<?php

namespace App\Actions\Admin;

use App\Domain\Authorization\AccessibleAdminStores;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

class SwitchAdminContext
{
    public function __construct(
        private readonly AccessibleAdminStores $accessibleStores,
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(User $user, int $storeId): Store
    {
        $store = $this->accessibleStores->forUser($user)->firstWhere('id', $storeId);

        if (! $store) {
            throw ValidationException::withMessages([
                'store_id' => 'Tanlangan filialga kirish huquqingiz yo‘q.',
            ]);
        }

        $organization = $this->tenantContext->resolveFor($user, $store->organization_id);
        $store = $this->storeContext->resolveFor($user, $this->tenantContext, $store->getKey());

        session([
            'current_organization_id' => $organization->getKey(),
            'current_store_id' => $store->getKey(),
        ]);

        return $store;
    }
}

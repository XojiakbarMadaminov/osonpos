<?php

namespace App\Actions\Pos;

use App\Domain\Catalog\CatalogRepository;
use App\Domain\Pos\PosConfigurationRepository;
use App\Domain\Shift\CurrentShift;
use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;

class BuildPosBootstrap
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly CatalogRepository $catalog,
        private readonly PosConfigurationRepository $configuration,
        private readonly SubscriptionAccess $subscriptions,
        private readonly CurrentShift $currentShift,
    ) {}

    public function execute(User $user): array
    {
        $organization = $this->tenantContext->requireCurrent();
        $store = $this->storeContext->requireCurrent();
        $device = $this->deviceContext->requireCurrent();
        $catalog = $this->catalog->forOrganization($organization);
        $configuration = $this->configuration->forStore($organization->getKey(), $store->getKey());
        $openOrdersByTableId = Order::query()
            ->where('organization_id', $organization->getKey())
            ->where('store_id', $store->getKey())
            ->where('status', OrderStatus::Open)
            ->whereNotNull('table_id')
            ->get(['id', 'table_id'])
            ->keyBy('table_id');
        $subscription = $this->subscriptions->activeSubscription($organization);
        $shift = $this->currentShift->for($user);

        return [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'store' => $store->only(['id', 'name', 'address', 'timezone']),
            'device' => $device->only(['id', 'name', 'code']),
            'user' => $user->only(['id', 'name', 'email']),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'features' => $subscription?->plan->features->pluck('code')->sort()->values()->all() ?? [],
            'categories' => collect($catalog)->map(fn (array $category): array => collect($category)->except('products')->all())->values()->all(),
            'products' => collect($catalog)->flatMap(fn (array $category): array => $category['products'])->values()->all(),
            'tables' => collect($configuration['tables'])->map(function (array $table) use ($openOrdersByTableId): array {
                $openOrder = $openOrdersByTableId->get($table['id']);

                return [
                    ...$table,
                    'is_occupied' => $openOrder !== null,
                    'open_order_id' => $openOrder?->getKey(),
                ];
            })->all(),
            'printers' => collect($configuration['printers'])
                ->filter(fn (array $printer): bool => $printer['device_id'] === null || $printer['device_id'] === $device->getKey())
                ->values()
                ->all(),
            'print_routes' => $configuration['print_routes'],
            'active_shift' => $shift ? $shift->only(['id', 'opening_cash', 'opened_at', 'status']) : null,
        ];
    }
}

<?php

namespace App\Domain\Pos;

use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Table;
use Illuminate\Support\Facades\Cache;

class PosConfigurationRepository
{
    public function forStore(int $organizationId, int $storeId): array
    {
        return Cache::remember($this->cacheKey($organizationId, $storeId), now()->addHour(), fn (): array => [
            'tables' => Table::query()
                ->where('organization_id', $organizationId)
                ->where('store_id', $storeId)
                ->where('is_active', true)
                ->orderBy('number')
                ->get(['id', 'name', 'number', 'capacity'])
                ->toArray(),
            'printers' => Printer::query()
                ->where('organization_id', $organizationId)
                ->where('store_id', $storeId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'device_id', 'name', 'system_name', 'paper_width'])
                ->toArray(),
            'print_routes' => PrintRoute::query()
                ->where('organization_id', $organizationId)
                ->where('store_id', $storeId)
                ->orderBy('print_type')
                ->get(['id', 'print_type', 'printer_id'])
                ->toArray(),
        ]);
    }

    public function forget(int $organizationId, int $storeId): void
    {
        Cache::forget($this->cacheKey($organizationId, $storeId));
    }

    private function cacheKey(int $organizationId, int $storeId): string
    {
        return "pos:configuration:organization:{$organizationId}:store:{$storeId}";
    }
}

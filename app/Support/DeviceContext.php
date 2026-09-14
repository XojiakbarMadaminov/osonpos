<?php

namespace App\Support;

use App\Models\Device;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class DeviceContext
{
    private ?Device $device = null;

    public function resolve(TenantContext $tenantContext, StoreContext $storeContext, ?string $deviceId): Device
    {
        $device = Device::query()
            ->whereKey($deviceId)
            ->where('organization_id', $tenantContext->requireCurrent()->getKey())
            ->where('store_id', $storeContext->requireCurrent()->getKey())
            ->where('is_active', true)
            ->first();

        if (! $device) {
            throw new AuthorizationException('Bu filial uchun faol qurilma aniqlanmadi.');
        }

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $this->device = $device;
    }

    public function current(): ?Device
    {
        return $this->device;
    }

    public function requireCurrent(): Device
    {
        return $this->device
            ?? throw new LogicException('Qurilma muhiti ishga tushirilmagan.');
    }

    public function clear(): void
    {
        $this->device = null;
    }
}

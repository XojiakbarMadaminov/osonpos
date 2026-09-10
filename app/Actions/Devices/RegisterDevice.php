<?php

namespace App\Actions\Devices;

use App\Models\Device;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterDevice
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(string $name, string $code): Device
    {
        return DB::transaction(function () use ($name, $code): Device {
            $organization = $this->tenantContext->requireCurrent();
            $store = $this->storeContext->requireCurrent();
            $normalizedCode = mb_strtoupper(trim($code));

            $device = Device::query()
                ->where('organization_id', $organization->getKey())
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if ($device && (int) $device->store_id !== (int) $store->getKey()) {
                throw ValidationException::withMessages([
                    'code' => 'This device code is already registered to another store.',
                ]);
            }

            if ($device && ! $device->is_active) {
                throw ValidationException::withMessages([
                    'code' => 'This device has been disabled by an administrator.',
                ]);
            }

            $device ??= new Device;
            $device->fill([
                'name' => $name,
                'code' => $normalizedCode,
                'last_seen_at' => now(),
            ]);
            $device->organization()->associate($organization);
            $device->store()->associate($store);
            $device->save();

            return $device;
        });
    }
}

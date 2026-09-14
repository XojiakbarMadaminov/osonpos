<?php

namespace App\Actions\Printing;

use App\Models\Printer;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

class BindPrinterToDevice
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
    ) {}

    public function execute(Printer $printer, string $systemName): Printer
    {
        $device = $this->deviceContext->requireCurrent();

        if ((int) $printer->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $printer->store_id !== (int) $this->storeContext->requireCurrent()->getKey()) {
            throw ValidationException::withMessages([
                'printer' => 'Printer joriy filialga tegishli emas.',
            ]);
        }

        $printer->forceFill([
            'device_id' => $device->getKey(),
            'system_name' => trim($systemName),
        ])->save();

        return $printer;
    }
}

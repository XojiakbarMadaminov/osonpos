<?php

namespace App\Domain\Printing;

use App\Enums\PrintType;
use App\Models\Device;
use App\Models\Printer;
use App\Models\Store;
use Illuminate\Validation\ValidationException;

class PrinterRoutingService
{
    public function resolve(PrintType $printType, Store $store, ?Device $device = null): Printer
    {
        $printer = Printer::query()
            ->where('printers.organization_id', $store->organization_id)
            ->where('printers.store_id', $store->getKey())
            ->where('printers.is_active', true)
            ->whereHas('printRoutes', fn ($query) => $query
                ->where('organization_id', $store->organization_id)
                ->where('store_id', $store->getKey())
                ->where('print_type', $printType->value))
            ->first();

        $deviceCanUsePrinter = $printer
            && ($printer->device_id === null || $device?->is($printer->device));

        if (! $printer || ! $deviceCanUsePrinter) {
            throw ValidationException::withMessages([
                'print_type' => "No active {$printType->value} printer is available for this device.",
            ]);
        }

        return $printer;
    }
}

<?php

namespace App\Filament\Admin\Resources\Printers\Pages;

use App\Filament\Admin\Resources\Printers\PrinterResource;
use App\Models\Printer;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePrinter extends CreateRecord
{
    protected static string $resource = PrinterResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(TenantContext::class)->requireCurrent();
        $store = app(StoreContext::class)->requireCurrent();

        $printer = new Printer($data);
        $printer->organization()->associate($tenant);
        $printer->store()->associate($store);
        $printer->save();

        return $printer;
    }
}

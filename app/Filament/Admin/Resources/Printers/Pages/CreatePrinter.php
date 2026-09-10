<?php

namespace App\Filament\Admin\Resources\Printers\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Printers\PrinterResource;
use App\Models\Printer;
use App\Models\Store;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePrinter extends CreateRecord
{
    protected static string $resource = PrinterResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(TenantContext::class)->requireCurrent();
        $store = Store::query()->forTenant($tenant)->findOrFail($data['store_id']);
        abort_unless(app(StoreAccess::class)->allows(request()->user(), $store), 403);

        $printer = new Printer($data);
        $printer->organization()->associate($tenant);
        $printer->store()->associate($store);
        $printer->save();

        return $printer;
    }
}

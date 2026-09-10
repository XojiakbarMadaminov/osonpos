<?php

namespace App\Filament\Admin\Resources\PrintRoutes\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\PrintRoutes\PrintRouteResource;
use App\Models\Printer;
use App\Models\Store;
use App\Support\TenantContext;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPrintRoute extends EditRecord
{
    protected static string $resource = PrintRouteResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $tenant = app(TenantContext::class)->requireCurrent();
        $store = Store::query()->forTenant($tenant)->findOrFail($data['store_id']);
        abort_unless(app(StoreAccess::class)->allows(request()->user(), $store), 403);
        $printer = Printer::query()
            ->forTenant($tenant)
            ->where('store_id', $store->getKey())
            ->findOrFail($data['printer_id']);

        $record->fill($data);
        $record->store()->associate($store);
        $record->printer()->associate($printer);
        $record->save();

        return $record;
    }
}

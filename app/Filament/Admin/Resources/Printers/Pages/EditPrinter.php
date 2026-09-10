<?php

namespace App\Filament\Admin\Resources\Printers\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Printers\PrinterResource;
use App\Models\Store;
use App\Support\TenantContext;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPrinter extends EditRecord
{
    protected static string $resource = PrinterResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $store = Store::query()->forTenant(app(TenantContext::class)->requireCurrent())->findOrFail($data['store_id']);
        abort_unless(app(StoreAccess::class)->allows(request()->user(), $store), 403);

        if ((int) $record->store_id !== (int) $store->getKey()) {
            $record->forceFill(['device_id' => null, 'system_name' => null]);
        }

        $record->fill($data);
        $record->store()->associate($store);
        $record->save();

        return $record;
    }
}

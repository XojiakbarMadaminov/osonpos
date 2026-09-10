<?php

namespace App\Filament\Admin\Resources\Tables\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Tables\TableResource;
use App\Models\Store;
use App\Support\TenantContext;
use Filament\Resources\Pages\EditRecord;

class EditTable extends EditRecord
{
    protected static string $resource = TableResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $store = Store::query()
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->findOrFail($data['store_id']);
        abort_unless(app(StoreAccess::class)->allows(request()->user(), $store), 403);

        return $data;
    }
}

<?php

namespace App\Filament\Admin\Resources\Tables\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Tables\TableResource;
use App\Models\Store;
use App\Models\Table;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTable extends CreateRecord
{
    protected static string $resource = TableResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(TenantContext::class)->requireCurrent();
        $store = Store::query()->forTenant($tenant)->findOrFail($data['store_id']);
        abort_unless(app(StoreAccess::class)->allows(request()->user(), $store), 403);

        $table = new Table($data);
        $table->organization()->associate($tenant);
        $table->store()->associate($store);
        $table->save();

        return $table;
    }
}

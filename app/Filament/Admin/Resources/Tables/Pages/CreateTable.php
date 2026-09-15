<?php

namespace App\Filament\Admin\Resources\Tables\Pages;

use App\Filament\Admin\Resources\Tables\TableResource;
use App\Models\Table;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTable extends CreateRecord
{
    protected static string $resource = TableResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(TenantContext::class)->requireCurrent();
        $store = app(StoreContext::class)->requireCurrent();

        $table = new Table($data);
        $table->organization()->associate($tenant);
        $table->store()->associate($store);
        $table->save();

        return $table;
    }
}

<?php

namespace App\Filament\Admin\Resources\Stores\Pages;

use App\Domain\Subscription\PlanLimits;
use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Models\Store;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $organization = app(TenantContext::class)->requireCurrent();
        app(PlanLimits::class)->ensureCanAddStore($organization);

        $store = new Store($data);
        $store->organization()->associate($organization);
        $store->save();

        return $store;
    }
}

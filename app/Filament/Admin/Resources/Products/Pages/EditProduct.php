<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Category;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Category::query()
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->forStore(app(StoreContext::class)->requireCurrent())
            ->findOrFail($data['category_id']);

        return $data;
    }
}

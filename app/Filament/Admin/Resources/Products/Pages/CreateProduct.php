<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(TenantContext::class)->requireCurrent();
        $store = app(StoreContext::class)->requireCurrent();
        $category = Category::query()->forTenant($tenant)->forStore($store)->findOrFail($data['category_id']);
        $product = new Product($data);
        $product->organization()->associate($tenant);
        $product->store()->associate($store);
        $product->category()->associate($category);
        $product->save();

        return $product;
    }
}

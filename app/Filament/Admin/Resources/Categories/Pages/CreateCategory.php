<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $category = new Category($data);
        $category->organization()->associate(app(TenantContext::class)->requireCurrent());
        $category->store()->associate(app(StoreContext::class)->requireCurrent());
        $category->save();

        return $category;
    }
}

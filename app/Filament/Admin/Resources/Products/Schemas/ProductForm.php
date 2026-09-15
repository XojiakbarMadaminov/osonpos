<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->hidden()
                    ->dehydrated(false),
                Select::make('category_id')
                    ->relationship(
                        'category',
                        'name',
                        fn ($query) => $query
                            ->forTenant(app(TenantContext::class)->requireCurrent())
                            ->forStore(app(StoreContext::class)->requireCurrent()),
                    )
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('price')
                    ->label('Sotuv narxi')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->prefix('UZS'),
                TextInput::make('cost_price')
                    ->label('Tannarx')
                    ->helperText('Bir dona mahsulotning taxminiy tannarxi')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('UZS'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

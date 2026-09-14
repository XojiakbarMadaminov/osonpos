<?php

namespace App\Filament\Admin\Resources\PrintRoutes\Schemas;

use App\Domain\Authorization\StoreAccess;
use App\Enums\PrintType;
use App\Support\TenantContext;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class PrintRouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('store_id')
                ->relationship('store', 'name', fn ($query) => $query->whereKey(
                    app(StoreAccess::class)->accessibleStoreIds(request()->user()),
                ))
                ->required(),
            Select::make('print_type')
                ->options(collect(PrintType::cases())->mapWithKeys(fn (PrintType $type): array => [
                    $type->value => $type->getLabel(),
                ])->all())
                ->required(),
            Select::make('printer_id')
                ->relationship('printer', 'name', fn ($query) => $query
                    ->forTenant(app(TenantContext::class)->requireCurrent())
                    ->whereIn('store_id', app(StoreAccess::class)->accessibleStoreIds(request()->user())))
                ->required(),
        ]);
    }
}

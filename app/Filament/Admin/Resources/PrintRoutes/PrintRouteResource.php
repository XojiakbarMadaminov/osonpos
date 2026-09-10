<?php

namespace App\Filament\Admin\Resources\PrintRoutes;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\PrintRoutes\Pages\CreatePrintRoute;
use App\Filament\Admin\Resources\PrintRoutes\Pages\EditPrintRoute;
use App\Filament\Admin\Resources\PrintRoutes\Pages\ListPrintRoutes;
use App\Filament\Admin\Resources\PrintRoutes\Schemas\PrintRouteForm;
use App\Filament\Admin\Resources\PrintRoutes\Tables\PrintRoutesTable;
use App\Models\PrintRoute;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrintRouteResource extends Resource
{
    protected static ?string $model = PrintRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function form(Schema $schema): Schema
    {
        return PrintRouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintRoutesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->whereIn('store_id', app(StoreAccess::class)->accessibleStoreIds(request()->user()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrintRoutes::route('/'),
            'create' => CreatePrintRoute::route('/create'),
            'edit' => EditPrintRoute::route('/{record}/edit'),
        ];
    }
}

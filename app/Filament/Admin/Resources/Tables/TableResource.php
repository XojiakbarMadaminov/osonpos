<?php

namespace App\Filament\Admin\Resources\Tables;

use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Tables\Pages\CreateTable;
use App\Filament\Admin\Resources\Tables\Pages\EditTable;
use App\Filament\Admin\Resources\Tables\Pages\ListTables;
use App\Filament\Admin\Resources\Tables\Schemas\TableForm;
use App\Filament\Admin\Resources\Tables\Tables\TablesTable;
use App\Models\Table as TableModel;
use App\Support\StoreContext;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TableResource extends Resource
{
    protected static ?string $model = TableModel::class;

    protected static ?string $modelLabel = 'stol';

    protected static ?string $pluralModelLabel = 'stollar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::BranchManagement;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return TableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $organization = app(TenantContext::class)->current();

        return parent::shouldRegisterNavigation()
            && $organization
            && app(SubscriptionAccess::class)->hasFeature($organization, 'tables');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('store')
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->forStore(app(StoreContext::class)->requireCurrent());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTables::route('/'),
            'create' => CreateTable::route('/create'),
            'edit' => EditTable::route('/{record}/edit'),
        ];
    }
}

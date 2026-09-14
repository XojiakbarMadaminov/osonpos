<?php

namespace App\Filament\Admin\Resources\Customers;

use App\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $modelLabel = 'mijoz';

    protected static ?string $pluralModelLabel = 'mijozlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Sales;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forTenant(app(TenantContext::class)->requireCurrent());
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomers::route('/')];
    }
}

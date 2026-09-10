<?php

namespace App\Filament\Admin\Resources\Devices;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Devices\Pages\ListDevices;
use App\Filament\Admin\Resources\Devices\Tables\DevicesTable;
use App\Models\Device;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    public static function table(Table $table): Table
    {
        return DevicesTable::configure($table);
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
            'index' => ListDevices::route('/'),
        ];
    }
}

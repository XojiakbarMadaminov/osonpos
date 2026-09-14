<?php

namespace App\Filament\Admin\Resources\Devices;

use App\Domain\Authorization\StoreAccess;
use App\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Devices\Pages\CreateDevice;
use App\Filament\Admin\Resources\Devices\Pages\ListDevices;
use App\Filament\Admin\Resources\Devices\Tables\DevicesTable;
use App\Models\Device;
use App\Models\Store;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $modelLabel = 'qurilma';

    protected static ?string $pluralModelLabel = 'qurilmalar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::BranchManagement;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('store_id')
                ->label('Filial')
                ->options(fn (): array => Store::query()
                    ->forTenant(app(TenantContext::class)->requireCurrent())
                    ->whereIn('id', app(StoreAccess::class)->accessibleStoreIds(request()->user()))
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->default(fn (): ?int => session('current_store_id'))
                ->required(),
            TextInput::make('name')
                ->label('Qurilma nomi')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->label('Qurilma kodi')
                ->helperText('Masalan: KASSA-01')
                ->required()
                ->alphaDash()
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return DevicesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('store')
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->whereIn('store_id', app(StoreAccess::class)->accessibleStoreIds(request()->user()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
        ];
    }
}

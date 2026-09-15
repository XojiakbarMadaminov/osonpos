<?php

namespace App\Filament\Admin\Resources\Devices;

use App\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Devices\Pages\CreateDevice;
use App\Filament\Admin\Resources\Devices\Pages\ListDevices;
use App\Filament\Admin\Resources\Devices\Tables\DevicesTable;
use App\Models\Device;
use App\Support\StoreContext;
use App\Support\TenantContext;
use BackedEnum;
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
            TextInput::make('name')
                ->label('Qurilma nomi')
                ->required()
                ->maxLength(255),
            TextInput::make('activation_code')
                ->label('Doimiy aktivatsiya kodi')
                ->helperText('6 xonali raqam kiriting. Kod uni almashtirmaguningizcha amal qiladi.')
                ->required()
                ->length(6)
                ->rules(['digits:6'])
                ->inputMode('numeric')
                ->extraInputAttributes(['pattern' => '[0-9]{6}'])
                ->password()
                ->revealable()
                ->dehydrated(),
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
            ->forStore(app(StoreContext::class)->requireCurrent());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
        ];
    }
}

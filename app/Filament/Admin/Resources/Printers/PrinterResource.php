<?php

namespace App\Filament\Admin\Resources\Printers;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Printers\Pages\CreatePrinter;
use App\Filament\Admin\Resources\Printers\Pages\EditPrinter;
use App\Filament\Admin\Resources\Printers\Pages\ListPrinters;
use App\Filament\Admin\Resources\Printers\Schemas\PrinterForm;
use App\Filament\Admin\Resources\Printers\Tables\PrintersTable;
use App\Models\Printer;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrinterResource extends Resource
{
    protected static ?string $model = Printer::class;

    protected static ?string $modelLabel = 'printer';

    protected static ?string $pluralModelLabel = 'printerlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    public static function form(Schema $schema): Schema
    {
        return PrinterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['store', 'device'])
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->whereIn('store_id', app(StoreAccess::class)->accessibleStoreIds(request()->user()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrinters::route('/'),
            'create' => CreatePrinter::route('/create'),
            'edit' => EditPrinter::route('/{record}/edit'),
        ];
    }
}

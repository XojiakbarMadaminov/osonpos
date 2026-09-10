<?php

namespace App\Filament\Admin\Resources\Printers\Schemas;

use App\Domain\Authorization\StoreAccess;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PrinterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('store_id')
                ->relationship('store', 'name', fn ($query) => $query->whereKey(
                    app(StoreAccess::class)->accessibleStoreIds(request()->user()),
                ))
                ->required(),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('paper_width')->options([58 => '58 mm', 80 => '80 mm'])->required()->default(80),
            Toggle::make('is_active')->required()->default(true),
        ]);
    }
}

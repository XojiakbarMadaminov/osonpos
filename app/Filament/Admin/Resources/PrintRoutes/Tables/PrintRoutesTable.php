<?php

namespace App\Filament\Admin\Resources\PrintRoutes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('store.name')->searchable(),
            TextColumn::make('print_type')->badge(),
            TextColumn::make('printer.name')->searchable(),
            TextColumn::make('printer.system_name')->placeholder('Biriktirilmagan'),
        ])->recordActions([EditAction::make()])->toolbarActions([]);
    }
}

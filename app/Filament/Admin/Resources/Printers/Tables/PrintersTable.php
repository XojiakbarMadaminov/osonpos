<?php

namespace App\Filament\Admin\Resources\Printers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('store.name')->searchable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('device.name')->placeholder('Biriktirilmagan'),
            TextColumn::make('system_name')->placeholder('Aniqlanmagan'),
            TextColumn::make('paper_width')->suffix(' mm'),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([EditAction::make()])->toolbarActions([]);
    }
}

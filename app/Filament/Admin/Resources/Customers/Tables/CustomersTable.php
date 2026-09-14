<?php

namespace App\Filament\Admin\Resources\Customers\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->placeholder('Nomsiz')->searchable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->recordActions([])->toolbarActions([]);
    }
}

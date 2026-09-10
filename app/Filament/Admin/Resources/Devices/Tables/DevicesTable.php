<?php

namespace App\Filament\Admin\Resources\Devices\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('code')->searchable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

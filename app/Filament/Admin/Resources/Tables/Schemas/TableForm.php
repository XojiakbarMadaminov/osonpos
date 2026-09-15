<?php

namespace App\Filament\Admin\Resources\Tables\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('number')
                    ->required(),
                TextInput::make('capacity')
                    ->integer()
                    ->minValue(1),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}

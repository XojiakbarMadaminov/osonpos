<?php

namespace App\Filament\Platform\Resources\Plans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('UZS'),
                Select::make('billing_period')
                    ->options([
                        'MONTHLY' => 'Oylik',
                        'YEARLY' => 'Yillik',
                    ])
                    ->required(),
                TextInput::make('max_stores')
                    ->required()
                    ->numeric(),
                TextInput::make('max_users')
                    ->required()
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
                Select::make('features')
                    ->relationship('features', 'name')
                    ->multiple()
                    ->preload(),
            ]);
    }
}

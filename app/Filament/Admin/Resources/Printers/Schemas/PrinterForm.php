<?php

namespace App\Filament\Admin\Resources\Printers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PrinterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('paper_width')->options([58 => '58 mm', 80 => '80 mm'])->required()->default(80),
            Toggle::make('is_active')->required()->default(true),
        ]);
    }
}

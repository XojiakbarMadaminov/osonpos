<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus: string implements HasColor, HasLabel
{
    case Active = 'ACTIVE';
    case Cancelled = 'CANCELLED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Faol',
            self::Cancelled => 'Bekor qilingan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'danger',
        };
    }
}

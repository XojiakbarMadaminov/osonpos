<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShiftStatus: string implements HasLabel
{
    case Open = 'OPEN';
    case Closed = 'CLOSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Ochiq',
            self::Closed => 'Yopilgan',
        };
    }
}

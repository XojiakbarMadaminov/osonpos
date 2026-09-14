<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Open = 'OPEN';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Ochiq',
            self::Completed => 'Yakunlangan',
            self::Cancelled => 'Bekor qilingan',
        };
    }
}

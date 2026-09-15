<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasColor, HasLabel
{
    case DineIn = 'DINE_IN';
    case Takeaway = 'TAKEAWAY';
    case Delivery = 'DELIVERY';

    public function getLabel(): string
    {
        return match ($this) {
            self::DineIn => 'Zalda',
            self::Takeaway => 'Olib ketish',
            self::Delivery => 'Yetkazib berish',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DineIn => 'success',
            self::Takeaway => 'warning',
            self::Delivery => 'info',
        };
    }
}

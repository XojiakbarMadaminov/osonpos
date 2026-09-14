<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel
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
}

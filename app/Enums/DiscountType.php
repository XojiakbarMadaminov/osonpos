<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percentage = 'PERCENTAGE';
    case Fixed = 'FIXED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percentage => 'Foiz',
            self::Fixed => 'Summa',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'CASH';
    case Card = 'CARD';
    case Click = 'CLICK';
    case Payme = 'PAYME';
    case Other = 'OTHER';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Naqd',
            self::Card => 'Karta',
            self::Click => 'Click',
            self::Payme => 'Payme',
            self::Other => 'Boshqa',
        };
    }
}

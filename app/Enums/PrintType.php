<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PrintType: string implements HasLabel
{
    case CustomerReceipt = 'CUSTOMER_RECEIPT';
    case KitchenTicket = 'KITCHEN_TICKET';

    public function getLabel(): string
    {
        return match ($this) {
            self::CustomerReceipt => 'Mijoz cheki',
            self::KitchenTicket => 'Oshxona cheki',
        };
    }
}

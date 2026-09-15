<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'UNPAID';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Refunded = 'REFUNDED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'To‘lanmagan',
            self::PartiallyPaid => 'Qisman to‘langan',
            self::Paid => 'To‘langan',
            self::Refunded => 'Qaytarilgan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Refunded => 'gray',
        };
    }
}

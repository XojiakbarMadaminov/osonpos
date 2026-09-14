<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpenseType: string implements HasLabel
{
    case ProductCost = 'PRODUCT_COST';
    case Rent = 'RENT';
    case Salary = 'SALARY';
    case Other = 'OTHER';

    public function getLabel(): string
    {
        return match ($this) {
            self::ProductCost => 'Mahsulotlar xarajati',
            self::Rent => 'Ijara',
            self::Salary => 'Oylik maosh',
            self::Other => 'Boshqa',
        };
    }
}

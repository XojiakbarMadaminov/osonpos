<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdminNavigationGroup implements HasLabel
{
    case Sales;
    case Catalog;
    case BranchManagement;
    case StaffAndPermissions;

    public function getLabel(): string
    {
        return match ($this) {
            self::Sales => 'Savdo',
            self::Catalog => 'Katalog',
            self::BranchManagement => 'Filial boshqaruvi',
            self::StaffAndPermissions => 'Xodimlar va ruxsatlar',
        };
    }
}

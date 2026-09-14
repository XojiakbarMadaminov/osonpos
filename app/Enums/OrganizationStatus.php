<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrganizationStatus: string implements HasLabel
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Faol',
            self::Suspended => 'To‘xtatilgan',
        };
    }
}

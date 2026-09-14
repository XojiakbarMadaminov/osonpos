<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel
{
    case Trial = 'TRIAL';
    case Active = 'ACTIVE';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Sinov muddati',
            self::Active => 'Faol',
            self::Expired => 'Muddati tugagan',
            self::Cancelled => 'Bekor qilingan',
        };
    }

    public function permitsTransactions(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}

<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'TRIAL';
    case Active = 'ACTIVE';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    public function permitsTransactions(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}

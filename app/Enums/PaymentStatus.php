<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'UNPAID';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Refunded = 'REFUNDED';
}

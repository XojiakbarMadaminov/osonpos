<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'OPEN';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}

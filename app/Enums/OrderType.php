<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'DINE_IN';
    case Takeaway = 'TAKEAWAY';
    case Delivery = 'DELIVERY';
}

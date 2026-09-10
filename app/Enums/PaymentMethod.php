<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'CASH';
    case Card = 'CARD';
    case Click = 'CLICK';
    case Payme = 'PAYME';
    case Other = 'OTHER';
}

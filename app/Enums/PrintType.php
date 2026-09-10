<?php

namespace App\Enums;

enum PrintType: string
{
    case CustomerReceipt = 'CUSTOMER_RECEIPT';
    case KitchenTicket = 'KITCHEN_TICKET';
}

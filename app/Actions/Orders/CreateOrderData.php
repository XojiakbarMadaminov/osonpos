<?php

namespace App\Actions\Orders;

use App\Enums\OrderType;

readonly class CreateOrderData
{
    public function __construct(
        public ?string $id,
        public OrderType $type,
        public ?int $tableId = null,
        public ?string $customerId = null,
        public ?string $customerPhone = null,
        public ?string $customerName = null,
        public ?string $note = null,
        public ?string $deliveryAddress = null,
        public int $deliveryFee = 0,
        public ?string $deliveryNote = null,
    ) {}
}

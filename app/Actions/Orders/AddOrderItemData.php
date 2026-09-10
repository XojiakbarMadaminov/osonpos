<?php

namespace App\Actions\Orders;

readonly class AddOrderItemData
{
    public function __construct(
        public ?string $id,
        public int $productId,
        public int $quantity,
        public ?string $note = null,
    ) {}
}

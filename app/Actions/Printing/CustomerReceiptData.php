<?php

namespace App\Actions\Printing;

use App\Models\Order;
use App\Models\Printer;

readonly class CustomerReceiptData
{
    public function __construct(
        public Order $order,
        public Printer $printer,
        public bool $isReprint,
    ) {}

    public function toArray(): array
    {
        $lines = collect([
            "Receipt {$this->order->display_number}",
            ...$this->order->items->map(fn ($item): string => "{$item->quantity}x {$item->product_name}  {$item->total}")->all(),
            "Subtotal: {$this->order->subtotal}",
        ]);

        if ($this->order->delivery_fee > 0) {
            $lines->push("Delivery: {$this->order->delivery_fee}");
        }

        $lines->push("TOTAL: {$this->order->total}");

        return [
            'order_id' => $this->order->getKey(),
            'printer' => [
                'id' => $this->printer->getKey(),
                'system_name' => $this->printer->system_name,
                'paper_width' => $this->printer->paper_width,
            ],
            'lines' => $lines->all(),
            'is_reprint' => $this->isReprint,
        ];
    }
}

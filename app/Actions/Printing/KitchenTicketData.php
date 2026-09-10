<?php

namespace App\Actions\Printing;

use App\Models\Printer;
use Illuminate\Support\Collection;

readonly class KitchenTicketData
{
    public function __construct(
        public string $orderId,
        public string $displayNumber,
        public Printer $printer,
        public Collection $items,
        public bool $isReprint,
    ) {}

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'display_number' => $this->displayNumber,
            'printer' => [
                'id' => $this->printer->getKey(),
                'system_name' => $this->printer->system_name,
                'paper_width' => $this->printer->paper_width,
            ],
            'item_ids' => $this->items->pluck('id')->all(),
            'lines' => $this->items->flatMap(function ($item): array {
                $lines = ["{$item->quantity}x {$item->product_name}"];

                if ($item->note) {
                    $lines[] = "  Note: {$item->note}";
                }

                return $lines;
            })->prepend("Kitchen {$this->displayNumber}")->values()->all(),
            'is_reprint' => $this->isReprint,
        ];
    }
}

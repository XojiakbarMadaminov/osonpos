<?php

namespace App\Actions\Printing;

use App\Domain\Printing\ThermalTextFormatter;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Printer;
use Illuminate\Support\Collection;

readonly class KitchenTicketData
{
    public function __construct(
        public Order $order,
        public Printer $printer,
        public Collection $items,
        public bool $isReprint,
    ) {}

    public function toArray(): array
    {
        $format = new ThermalTextFormatter($this->printer->paper_width);
        $type = match ($this->order->type) {
            OrderType::DineIn => 'ZALDA',
            OrderType::Takeaway => 'OLIB KETISH',
            OrderType::Delivery => 'YETKAZIB BERISH',
        };
        $time = ($this->order->opened_at ?? $this->order->created_at)
            ->setTimezone($this->order->store->timezone)
            ->format('d.m.Y H:i');
        $lines = [
            $format->center('OSHXONA BUYURTMASI'),
            $format->center($this->order->display_number),
            $format->separator('='),
            $format->columns('TURI', $type),
        ];

        if ($this->order->table) {
            $lines[] = $format->columns('STOL', $this->order->table->number);
        }

        $lines[] = $format->columns('VAQT', $time);
        $lines[] = $format->columns('KASSIR', $this->order->creator->name);
        $lines[] = $format->separator();

        foreach ($this->items as $item) {
            array_push($lines, ...$format->wrap("{$item->quantity} x ".mb_strtoupper($item->product_name)));

            if ($item->note) {
                array_push($lines, ...$format->wrap("! {$item->note}", 2));
            }
        }

        $lines[] = $format->separator('=');
        $lines[] = $format->columns('JAMI MAHSULOT', (string) $this->items->sum('quantity'));

        if ($this->order->note) {
            $lines[] = $format->separator();
            array_push($lines, ...$format->wrap("BUYURTMA IZOHI: {$this->order->note}"));
        }

        return [
            'order_id' => $this->order->getKey(),
            'display_number' => $this->order->display_number,
            'printer' => [
                'id' => $this->printer->getKey(),
                'system_name' => $this->printer->system_name,
                'paper_width' => $this->printer->paper_width,
            ],
            'item_ids' => $this->items->pluck('id')->all(),
            'lines' => $lines,
            'is_reprint' => $this->isReprint,
        ];
    }
}

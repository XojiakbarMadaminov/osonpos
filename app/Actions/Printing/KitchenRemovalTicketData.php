<?php

namespace App\Actions\Printing;

use App\Domain\Printing\ThermalTextFormatter;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Printer;
use Illuminate\Support\Collection;

readonly class KitchenRemovalTicketData
{
    public function __construct(
        public Order $order,
        public Printer $printer,
        public Collection $removals,
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
        $time = now($this->order->store->timezone)->format('d.m.Y H:i');
        $lines = [
            $format->center('MAHSULOT BEKOR QILINDI'),
            "\x1B\x21\x38".str_repeat(' ', max(0, intdiv(intdiv($format->width, 2) - mb_strwidth($this->order->display_number), 2))).$this->order->display_number."\x1B\x21\x00",
            $format->separator('='),
            $format->columns('TURI', $type),
        ];

        if ($this->order->table) {
            $lines[] = $format->columns('STOL', $this->order->table->number);
        }

        $lines[] = $format->columns('VAQT', $time);
        $lines[] = $format->separator();

        foreach ($this->removals as $removal) {
            array_push($lines, ...$format->wrap("{$removal->quantity} x ".mb_strtoupper($removal->orderItem->product_name)));
        }

        $lines[] = $format->separator('=');
        $lines[] = $format->columns('JAMI AYIRILDI', (string) $this->removals->sum('quantity'));

        return [
            'order_id' => $this->order->getKey(),
            'display_number' => $this->order->display_number,
            'printer' => [
                'id' => $this->printer->getKey(),
                'system_name' => $this->printer->system_name,
                'paper_width' => $this->printer->paper_width,
            ],
            'removal_ids' => $this->removals->pluck('id')->all(),
            'lines' => $lines,
            'is_reprint' => $this->isReprint,
        ];
    }
}

<?php

namespace App\Actions\Printing;

use App\Domain\Printing\ThermalTextFormatter;
use App\Enums\OrderType;
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
        $format = new ThermalTextFormatter($this->printer->paper_width);
        $type = match ($this->order->type) {
            OrderType::DineIn => 'ZALDA',
            OrderType::Takeaway => 'OLIB KETISH',
            OrderType::Delivery => 'YETKAZIB BERISH',
        };
        $time = ($this->order->closed_at ?? $this->order->updated_at)
            ->setTimezone($this->order->store->timezone)
            ->format('d.m.Y H:i');
        $lines = collect([
            $format->center(mb_strtoupper($this->order->store->name)),
        ]);

        if ($this->order->store->address) {
            foreach ($format->wrap($this->order->store->address) as $addressLine) {
                $lines->push($format->center($addressLine));
            }
        }

        if ($this->order->store->phone) {
            $lines->push($format->center($this->order->store->phone));
        }

        $lines->push(
            $format->center('SAVDO CHEKI'),
            $format->separator('='),
            $format->columns('BUYURTMA', $this->order->display_number),
            $format->columns('SANA', $time),
            $format->columns('KASSIR', $this->order->creator->name),
            $format->columns('TURI', $type),
        );

        if ($this->order->table) {
            $lines->push($format->columns('STOL', $this->order->table->number));
        }

        if ($this->order->customer) {
            if ($this->order->customer->name) {
                $lines->push($format->columns('MIJOZ', $this->order->customer->name));
            }

            $lines->push($format->columns('TELEFON', $this->order->customer->phone));
        }

        $lines->push($format->separator());

        foreach ($this->order->items as $item) {
            $lines->push(...$format->wrap($item->product_name));
            $lines->push($format->columns(
                "  {$item->quantity} x {$format->money($item->unit_price)}",
                $format->money($item->total),
            ));
        }

        $lines->push(
            $format->separator(),
            $format->columns('ORALIQ JAMI', $format->money($this->order->subtotal)),
        );

        if ($this->order->delivery_fee > 0) {
            $lines->push($format->columns('YETKAZIB BERISH', $format->money($this->order->delivery_fee)));
        }

        $lines->push(
            $format->separator('='),
            $format->columns('JAMI UZS', $format->money($this->order->total)),
            $format->separator('='),
            $format->center('TO‘LOV'),
        );

        foreach ($this->order->payments->groupBy(fn ($payment) => $payment->method->value) as $method => $payments) {
            $methodLabel = match ($method) {
                'CASH' => 'NAQD',
                'CARD' => 'KARTA',
                'OTHER' => 'BOSHQA',
                default => $method,
            };
            $lines->push($format->columns($methodLabel, $format->money($payments->sum('amount'))));
        }

        if ($this->order->type === OrderType::Delivery && $this->order->deliveryDetail) {
            $lines->push($format->separator());
            $lines->push(...$format->wrap("YETKAZISH MANZILI: {$this->order->deliveryDetail->address}"));
        }

        $lines->push(
            '',
            $format->center('XARIDINGIZ UCHUN RAHMAT!'),
            $format->center('OsonPOS tizimi'),
        );

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

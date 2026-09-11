<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidAmount = (int) ($this->payments_sum_amount ?? 0);

        return [
            'id' => $this->getKey(),
            'display_number' => $this->display_number,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'table_id' => $this->table_id,
            'table' => $this->when(
                $this->relationLoaded('table'),
                fn (): ?array => $this->table?->only(['id', 'name', 'number']),
                null,
            ),
            'customer_id' => $this->customer_id,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'paid_amount' => $paidAmount,
            'balance_due' => max(0, $this->total - $paidAmount),
            'note' => $this->note,
            'delivery' => $this->whenLoaded('deliveryDetail', fn (): array => [
                'address' => $this->deliveryDetail->address,
                'delivery_fee' => $this->deliveryDetail->delivery_fee,
                'note' => $this->deliveryDetail->note,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->getKey(),
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'note' => $item->note,
            ])),
        ];
    }
}

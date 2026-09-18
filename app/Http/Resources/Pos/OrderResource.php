<?php

namespace App\Http\Resources\Pos;

use App\Support\StoreBusinessDate;
use App\Support\StoreContext;
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
            'business_date' => $this->business_date->toDateString(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'is_current_business_date' => $this->business_date->toDateString() === app(StoreBusinessDate::class)
                ->current(app(StoreContext::class)->requireCurrent()),
            'table_id' => $this->table_id,
            'table' => $this->when(
                $this->relationLoaded('table'),
                fn (): ?array => $this->table?->only(['id', 'name', 'number']),
                null,
            ),
            'customer_id' => $this->customer_id,
            'customer' => $this->customer_phone ? [
                'id' => $this->customer_id,
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
            ] : null,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'paid_amount' => $paidAmount,
            'balance_due' => max(0, $this->total - $paidAmount),
            'unprinted_items_count' => (int) ($this->unprinted_items_count ?? 0),
            'pending_item_removals_count' => (int) ($this->pending_item_removals_count ?? 0),
            'note' => $this->note,
            'delivery' => $this->whenLoaded('deliveryDetail', fn (): array => [
                'address' => $this->deliveryDetail->address,
                'delivery_fee' => $this->deliveryDetail->delivery_fee,
                'note' => $this->deliveryDetail->note,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items
                ->filter(fn ($item): bool => $item->remainingQuantity() > 0)
                ->values()
                ->map(fn ($item): array => [
                    'id' => $item->getKey(),
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'original_quantity' => $item->quantity,
                    'removed_quantity' => $item->removedQuantity(),
                    'quantity' => $item->remainingQuantity(),
                    'unit_price' => $item->unit_price,
                    'total' => $item->remainingTotal(),
                    'note' => $item->note,
                    'kitchen_printed' => $item->kitchen_printed_at !== null,
                ])),
        ];
    }
}

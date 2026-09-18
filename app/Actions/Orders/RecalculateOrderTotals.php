<?php

namespace App\Actions\Orders;

use App\Enums\DiscountType;
use App\Models\Order;

class RecalculateOrderTotals
{
    public function execute(Order $order): Order
    {
        $itemsTotal = (int) $order->items()->sum('total');
        $removedTotal = (int) $order->itemRemovals()->sum('total');
        $subtotal = max(0, $itemsTotal - $removedTotal);
        $discountAmount = match ($order->discount_type) {
            DiscountType::Percentage => intdiv($subtotal * (int) $order->discount_value, 100),
            DiscountType::Fixed => min($subtotal, (int) $order->discount_value),
            null => 0,
        };

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $subtotal - $discountAmount + $order->delivery_fee,
        ])->save();

        return $order;
    }
}

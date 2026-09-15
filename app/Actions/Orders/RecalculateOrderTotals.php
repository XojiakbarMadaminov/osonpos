<?php

namespace App\Actions\Orders;

use App\Models\Order;

class RecalculateOrderTotals
{
    public function execute(Order $order): Order
    {
        $itemsTotal = (int) $order->items()->sum('total');
        $removedTotal = (int) $order->itemRemovals()->sum('total');
        $subtotal = max(0, $itemsTotal - $removedTotal);

        $order->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal + $order->delivery_fee,
        ])->save();

        return $order;
    }
}

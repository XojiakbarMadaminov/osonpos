<?php

namespace App\Actions\Printing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkKitchenItemsPrinted
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, array $itemIds): int
    {
        return DB::transaction(function () use ($order, $itemIds): int {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
                || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
                || $order->status !== OrderStatus::Open) {
                throw ValidationException::withMessages(['order' => 'This order cannot be confirmed in the current context.']);
            }

            $items = OrderItem::query()
                ->where('order_id', $order->getKey())
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count(array_unique($itemIds))) {
                throw ValidationException::withMessages(['item_ids' => 'Every confirmed item must belong to this order.']);
            }

            return OrderItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->whereNull('kitchen_printed_at')
                ->update(['kitchen_printed_at' => now(), 'updated_at' => now()]);
        });
    }
}

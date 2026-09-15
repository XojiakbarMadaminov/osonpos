<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\RemoveOrderItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\RemoveOrderItemsRequest;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

class OrderItemRemovalController extends Controller
{
    public function __invoke(
        RemoveOrderItemsRequest $request,
        Order $order,
        RemoveOrderItems $removeOrderItems,
    ): OrderResource {
        Gate::authorize('update', $order);
        $order = $removeOrderItems->execute($order, $request->user(), $request->validated('items'));

        return new OrderResource(
            $order->load(['items.removals', 'deliveryDetail', 'table'])
                ->loadSum('payments', 'amount')
                ->loadCount([
                    'items as unprinted_items_count' => fn ($query) => $query
                        ->whereNull('kitchen_printed_at')
                        ->whereRaw('order_items.quantity > COALESCE((SELECT SUM(order_item_removals.quantity) FROM order_item_removals WHERE order_item_removals.order_item_id = order_items.id), 0)'),
                    'itemRemovals as pending_item_removals_count' => fn ($query) => $query
                        ->where('kitchen_print_required', true)
                        ->whereNull('kitchen_printed_at'),
                ]),
        );
    }
}

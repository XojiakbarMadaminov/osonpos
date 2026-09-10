<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\AddOrderItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\AddOrderItemRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderItemController extends Controller
{
    public function __invoke(
        AddOrderItemRequest $request,
        Order $order,
        AddOrderItem $addOrderItem,
    ): JsonResponse {
        Gate::authorize('update', $order);
        $validated = $request->validated();
        $item = $addOrderItem->execute($order, $request->user(), new AddOrderItemData(
            id: $validated['id'] ?? null,
            productId: $validated['product_id'],
            quantity: $validated['quantity'],
            note: $validated['note'] ?? null,
        ));

        return response()->json(['data' => [
            'id' => $item->getKey(),
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total' => $item->total,
        ]], 201);
    }
}

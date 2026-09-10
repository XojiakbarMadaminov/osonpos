<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CancelOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($cancelOrder->execute($order, $request->user()));
    }
}

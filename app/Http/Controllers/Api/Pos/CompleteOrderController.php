<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\CompleteOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompleteOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, CompleteOrder $complete): OrderResource
    {
        Gate::authorize('update', $order);

        return new OrderResource($complete->execute($order, $request->user()));
    }
}

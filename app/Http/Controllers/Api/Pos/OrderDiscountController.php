<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\SetOpenOrderDiscount;
use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SetOrderDiscountRequest;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderDiscountController extends Controller
{
    public function update(
        SetOrderDiscountRequest $request,
        Order $order,
        SetOpenOrderDiscount $setDiscount,
    ): OrderResource {
        Gate::authorize('update', $order);

        return $this->resource($setDiscount->execute(
            $order,
            $request->user(),
            DiscountType::from($request->validated('discount_type')),
            $request->integer('discount_value'),
        ));
    }

    public function destroy(
        Request $request,
        Order $order,
        SetOpenOrderDiscount $setDiscount,
    ): OrderResource {
        Gate::authorize('update', $order);

        return $this->resource($setDiscount->execute($order, $request->user(), null, null));
    }

    private function resource(Order $order): OrderResource
    {
        return new OrderResource(
            $order->load('table')->loadSum('payments', 'amount'),
        );
    }
}

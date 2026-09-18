<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\MoveOpenDineInOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\MoveOrderTableRequest;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

class MoveOrderTableController extends Controller
{
    public function __invoke(
        MoveOrderTableRequest $request,
        Order $order,
        MoveOpenDineInOrder $moveOrder,
    ): OrderResource {
        Gate::authorize('update', $order);

        return new OrderResource(
            $moveOrder->execute($order, $request->integer('table_id'))->load('table'),
        );
    }
}

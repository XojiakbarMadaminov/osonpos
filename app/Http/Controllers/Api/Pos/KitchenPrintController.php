<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Printing\MarkKitchenItemsPrinted;
use App\Actions\Printing\PrepareKitchenTicket;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ConfirmKitchenPrintRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class KitchenPrintController extends Controller
{
    public function prepare(Order $order, PrepareKitchenTicket $prepare): JsonResponse
    {
        Gate::authorize('update', $order);

        return response()->json(['data' => $prepare->execute($order)->toArray()]);
    }

    public function confirm(
        ConfirmKitchenPrintRequest $request,
        Order $order,
        MarkKitchenItemsPrinted $markPrinted,
    ): JsonResponse {
        Gate::authorize('update', $order);
        $updated = $markPrinted->execute($order, $request->validated('item_ids'));

        return response()->json(['data' => ['marked_printed' => $updated]]);
    }

    public function reprint(Order $order, PrepareKitchenTicket $prepare): JsonResponse
    {
        Gate::authorize(OrganizationPermission::OrdersReprint->value);
        Gate::authorize('view', $order);

        return response()->json(['data' => $prepare->execute($order, reprint: true)->toArray()]);
    }
}

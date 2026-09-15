<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Printing\MarkKitchenRemovalsPrinted;
use App\Actions\Printing\PrepareKitchenRemovalTicket;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ConfirmKitchenRemovalPrintRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class KitchenRemovalPrintController extends Controller
{
    public function prepare(Order $order, PrepareKitchenRemovalTicket $prepare): JsonResponse
    {
        Gate::authorize('update', $order);

        return response()->json(['data' => $prepare->execute($order)->toArray()]);
    }

    public function confirm(
        ConfirmKitchenRemovalPrintRequest $request,
        Order $order,
        MarkKitchenRemovalsPrinted $markPrinted,
    ): JsonResponse {
        Gate::authorize('update', $order);
        $updated = $markPrinted->execute($order, $request->validated('removal_ids'));

        return response()->json(['data' => ['marked_printed' => $updated]]);
    }

    public function reprint(Order $order, PrepareKitchenRemovalTicket $prepare): JsonResponse
    {
        Gate::authorize(OrganizationPermission::OrdersReprint->value);
        Gate::authorize('view', $order);

        return response()->json(['data' => $prepare->execute($order, reprint: true)->toArray()]);
    }
}

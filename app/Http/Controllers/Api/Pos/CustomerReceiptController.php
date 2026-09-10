<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Printing\PrepareCustomerReceipt;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomerReceiptController extends Controller
{
    public function prepare(Order $order, PrepareCustomerReceipt $prepare): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json(['data' => $prepare->execute($order)->toArray()]);
    }

    public function reprint(Order $order, PrepareCustomerReceipt $prepare): JsonResponse
    {
        Gate::authorize(OrganizationPermission::OrdersReprint->value);
        Gate::authorize('view', $order);

        return response()->json(['data' => $prepare->execute($order, reprint: true)->toArray()]);
    }
}

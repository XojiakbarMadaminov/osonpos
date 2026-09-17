<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Payments\CreateMixedPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CreateMixedPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreateMixedPaymentController extends Controller
{
    public function __invoke(
        CreateMixedPaymentRequest $request,
        Order $order,
        CreateMixedPayment $createMixedPayment,
    ): JsonResponse {
        Gate::authorize('view', $order);
        Gate::authorize('create', Payment::class);
        $validated = $request->validated();
        $payments = $createMixedPayment->execute(
            order: $order,
            user: $request->user(),
            cashPaymentId: $validated['cash_payment_id'],
            cashAmount: $validated['cash_amount'],
            cardPaymentId: $validated['card_payment_id'],
            cardAmount: $validated['card_amount'],
        );

        return response()->json(['data' => [
            'cash_payment_id' => $payments['cash']->getKey(),
            'card_payment_id' => $payments['card']->getKey(),
        ]], $payments['cash']->wasRecentlyCreated ? 201 : 200);
    }
}

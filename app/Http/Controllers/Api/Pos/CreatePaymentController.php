<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Payments\CreatePayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CreatePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreatePaymentController extends Controller
{
    public function __invoke(
        CreatePaymentRequest $request,
        Order $order,
        CreatePayment $createPayment,
    ): JsonResponse {
        Gate::authorize('view', $order);
        Gate::authorize('create', Payment::class);
        $validated = $request->validated();
        $payment = $createPayment->execute(
            order: $order,
            user: $request->user(),
            method: PaymentMethod::from($validated['method']),
            amount: $validated['amount'],
            id: $validated['id'] ?? null,
        );
        $order->refresh();
        $paidAmount = (int) $order->payments()->sum('amount');

        return response()->json(['data' => [
            'id' => $payment->getKey(),
            'method' => $payment->method->value,
            'amount' => $payment->amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, $order->total - $paidAmount),
            'payment_status' => $order->payment_status->value,
        ]], $payment->wasRecentlyCreated ? 201 : 200);
    }
}

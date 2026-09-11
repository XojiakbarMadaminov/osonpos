<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\OpenShift;
use App\Domain\Shift\CurrentShift;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CloseShiftRequest;
use App\Http\Requests\Api\Pos\OpenShiftRequest;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShiftController extends Controller
{
    public function current(Request $request, CurrentShift $currentShift): JsonResponse
    {
        Gate::authorize('viewAny', Shift::class);

        return response()->json(['data' => $this->serialize($currentShift->for($request->user()))]);
    }

    public function store(OpenShiftRequest $request, OpenShift $openShift): JsonResponse
    {
        Gate::authorize('create', Shift::class);
        $shift = $openShift->execute($request->user(), $request->validated('opening_cash'));

        return response()->json(['data' => $this->serialize($shift)], 201);
    }

    public function close(CloseShiftRequest $request, Shift $shift, CloseShift $closeShift): JsonResponse
    {
        Gate::authorize('update', $shift);
        $shift = $closeShift->execute($shift, $request->user(), $request->validated('closing_cash'));

        return response()->json(['data' => $this->serialize($shift)]);
    }

    private function serialize(?Shift $shift): ?array
    {
        if (! $shift) {
            return null;
        }

        $paymentTotals = $shift->payments()
            ->selectRaw('method, SUM(amount) AS total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn (mixed $total): int => (int) $total);
        $cashPayments = $paymentTotals->get(PaymentMethod::Cash->value, 0);
        $expectedCash = $shift->opening_cash + $cashPayments;

        return [
            'id' => $shift->getKey(),
            'status' => $shift->status->value,
            'opening_cash' => $shift->opening_cash,
            'closing_cash' => $shift->closing_cash,
            'opened_at' => $shift->opened_at,
            'closed_at' => $shift->closed_at,
            'payment_totals' => collect(PaymentMethod::cases())
                ->mapWithKeys(fn (PaymentMethod $method): array => [
                    $method->value => $paymentTotals->get($method->value, 0),
                ])->all(),
            'payments_total' => $paymentTotals->sum(),
            'cash_payments_total' => $cashPayments,
            'expected_cash' => $expectedCash,
            'cash_difference' => $shift->closing_cash === null ? null : $shift->closing_cash - $expectedCash,
        ];
    }
}

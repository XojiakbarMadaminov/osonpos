<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\OpenShift;
use App\Domain\Shift\CurrentShift;
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
        return $shift ? [
            'id' => $shift->getKey(),
            'status' => $shift->status->value,
            'opening_cash' => $shift->opening_cash,
            'closing_cash' => $shift->closing_cash,
            'opened_at' => $shift->opened_at,
            'closed_at' => $shift->closed_at,
        ] : null;
    }
}

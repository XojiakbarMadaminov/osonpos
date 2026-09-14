<?php

namespace App\Actions\Shifts;

use App\Enums\OrderStatus;
use App\Enums\ShiftStatus;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CloseShift
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
    ) {}

    public function execute(Shift $shift, User $user, int $closingCash): Shift
    {
        return DB::transaction(function () use ($shift, $user, $closingCash): Shift {
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->getKey());
            $isCurrent = (int) $shift->organization_id === (int) $this->tenantContext->requireCurrent()->getKey()
                && (int) $shift->store_id === (int) $this->storeContext->requireCurrent()->getKey()
                && $shift->device_id === $this->deviceContext->requireCurrent()->getKey()
                && (int) $shift->user_id === (int) $user->getKey()
                && $shift->status === ShiftStatus::Open;

            if (! $isCurrent) {
                throw ValidationException::withMessages(['shift' => 'Faqat joriy qurilmaning smenasini yopish mumkin.']);
            }

            return $this->finalize($shift, $closingCash);
        });
    }

    public function executeAsSupervisor(Shift $shift, User $supervisor, int $closingCash): Shift
    {
        Gate::forUser($supervisor)->authorize('closeAsSupervisor', $shift);

        return DB::transaction(function () use ($shift, $closingCash): Shift {
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->getKey());

            if ($shift->status !== ShiftStatus::Open) {
                throw ValidationException::withMessages([
                    'shift' => 'Faqat ochiq smenani yopish mumkin.',
                ]);
            }

            return $this->finalize($shift, $closingCash);
        });
    }

    private function finalize(Shift $shift, int $closingCash): Shift
    {
        $hasOpenOrders = Order::query()
            ->where('organization_id', $shift->organization_id)
            ->where('store_id', $shift->store_id)
            ->where('device_id', $shift->device_id)
            ->where('status', OrderStatus::Open)
            ->exists();

        if ($hasOpenOrders) {
            throw ValidationException::withMessages([
                'shift' => 'Smenani yopishdan oldin bu qurilmadagi ochiq buyurtmalarni yoping yoki bekor qiling.',
            ]);
        }

        $shift->forceFill([
            'closing_cash' => $closingCash,
            'closed_at' => now(),
            'status' => ShiftStatus::Closed,
        ])->save();

        return $shift;
    }
}

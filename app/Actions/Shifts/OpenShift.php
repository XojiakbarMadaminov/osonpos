<?php

namespace App\Actions\Shifts;

use App\Domain\Shift\CurrentShift;
use App\Enums\ShiftStatus;
use App\Models\Device;
use App\Models\Shift;
use App\Models\User;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenShift
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly CurrentShift $currentShift,
    ) {}

    public function execute(User $user, int $openingCash): Shift
    {
        return DB::transaction(function () use ($user, $openingCash): Shift {
            $device = Device::query()->lockForUpdate()->findOrFail($this->deviceContext->requireCurrent()->getKey());

            if ($this->currentShift->for($user)
                || Shift::query()->where('organization_id', $this->tenantContext->requireCurrent()->getKey())
                    ->where('user_id', $user->getKey())
                    ->where('status', ShiftStatus::Open)
                    ->exists()
                || Shift::query()->where('device_id', $device->getKey())->where('status', ShiftStatus::Open)->exists()) {
                throw ValidationException::withMessages(['shift' => 'Bu foydalanuvchi yoki qurilmada allaqachon ochiq smena bor.']);
            }

            $shift = new Shift(['opening_cash' => $openingCash]);
            $shift->organization()->associate($this->tenantContext->requireCurrent());
            $shift->store()->associate($this->storeContext->requireCurrent());
            $shift->device()->associate($device);
            $shift->user()->associate($user);
            $shift->forceFill([
                'status' => ShiftStatus::Open,
                'opened_at' => now(),
            ])->save();

            return $shift;
        });
    }
}

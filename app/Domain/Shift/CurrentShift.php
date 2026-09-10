<?php

namespace App\Domain\Shift;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;

class CurrentShift
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
    ) {}

    public function for(User $user): ?Shift
    {
        return Shift::query()
            ->where('organization_id', $this->tenantContext->requireCurrent()->getKey())
            ->where('store_id', $this->storeContext->requireCurrent()->getKey())
            ->where('device_id', $this->deviceContext->requireCurrent()->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', ShiftStatus::Open)
            ->first();
    }
}

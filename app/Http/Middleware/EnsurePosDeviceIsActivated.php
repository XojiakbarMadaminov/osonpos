<?php

namespace App\Http\Middleware;

use App\Support\DeviceContext;
use App\Support\DeviceCredential;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosDeviceIsActivated
{
    public function __construct(
        private readonly DeviceCredential $credential,
        private readonly DeviceContext $deviceContext,
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->credential->resolve($request);

        if (! $device
            || ! $device->belongsToTenant($this->tenantContext->requireCurrent())
            || ! $device->belongsToStore($this->storeContext->requireCurrent())) {
            return redirect()->route('pos.device-setup');
        }

        $this->deviceContext->set($device);

        return $next($request);
    }
}

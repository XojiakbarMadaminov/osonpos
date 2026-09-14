<?php

namespace App\Http\Middleware;

use App\Support\DeviceContext;
use App\Support\DeviceCredential;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeDeviceContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly DeviceCredential $credential,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $device = $this->credential->resolve($request);

            if (! $device
                || ! $device->belongsToTenant($this->tenantContext->requireCurrent())
                || ! $device->belongsToStore($this->storeContext->requireCurrent())) {
                throw new AuthorizationException('Bu filial uchun faol qurilma aniqlanmadi.');
            }

            $this->deviceContext->set($device);
        } else {
            $this->deviceContext->clear();
        }

        return $next($request);
    }
}

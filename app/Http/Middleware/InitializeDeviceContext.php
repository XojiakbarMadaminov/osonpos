<?php

namespace App\Http\Middleware;

use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeDeviceContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->deviceContext->resolve(
                $this->tenantContext,
                $this->storeContext,
                $request->hasSession() ? $request->session()->get('current_device_id') : null,
            );
        } else {
            $this->deviceContext->clear();
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\StoreContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeStoreContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->storeContext->resolveFor(
                $request->user(),
                $this->tenantContext,
                $request->hasSession() ? $request->session()->get('current_store_id') : null,
            );
        } else {
            $this->storeContext->clear();
        }

        return $next($request);
    }
}

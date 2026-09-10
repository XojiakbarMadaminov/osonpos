<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $organization = $this->tenantContext->resolveFor(
                $request->user(),
                $request->hasSession() ? $request->session()->get('current_organization_id') : null,
            );

            $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
            $this->permissionRegistrar->setPermissionsTeamId($organization->getKey());
            $request->user()->unsetRelation('roles')->unsetRelation('permissions');

            try {
                return $next($request);
            } finally {
                $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
            }
        } else {
            $this->tenantContext->clear();
        }

        return $next($request);
    }
}

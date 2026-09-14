<?php

namespace App\Http\Middleware;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Device;
use App\Models\User;
use App\Support\DeviceCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestoreDeviceSessionContext
{
    public function __construct(
        private readonly DeviceCredential $credential,
        private readonly OrganizationAuthorization $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $device = $this->credential->resolve($request);

        if ($device
            && (int) $request->session()->get('current_organization_id') === (int) $device->organization_id
            && (int) $request->session()->get('current_store_id') === (int) $device->store_id) {
            return $next($request);
        }

        if ($user && $device && $this->userCanUse($user, $device)) {
            $request->session()->put([
                'current_organization_id' => $device->organization_id,
                'current_store_id' => $device->store_id,
            ]);
        }

        return $next($request);
    }

    private function userCanUse(User $user, Device $device): bool
    {
        if (! $device->store()->where('is_active', true)->exists()) {
            return false;
        }

        if (! $user->organizations()->whereKey($device->organization_id)->exists()) {
            return false;
        }

        $hasPosAccess = $this->authorization->runForUserInTenant(
            $user,
            $device->organization_id,
            fn (User $tenantUser): bool => $tenantUser->can(OrganizationPermission::PosAccess->value),
        );
        $isOwner = $this->authorization->runForUserInTenant(
            $user,
            $device->organization_id,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );

        return $hasPosAccess
            && ($isOwner || $user->stores()->whereKey($device->store_id)->exists());
    }
}

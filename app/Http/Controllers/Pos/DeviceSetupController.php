<?php

namespace App\Http\Controllers\Pos;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Support\DeviceCredential;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DeviceSetupController extends Controller
{
    public function __invoke(
        Request $request,
        OrganizationAuthorization $authorization,
        DeviceCredential $credential,
        TenantContext $tenantContext,
        StoreContext $storeContext,
    ): View {
        abort_unless(
            $authorization->allows($request->user(), OrganizationPermission::PosAccess),
            403,
        );

        $device = $credential->resolve($request);
        if ($device && (! $device->belongsToTenant($tenantContext->requireCurrent())
            || ! $device->belongsToStore($storeContext->requireCurrent()))) {
            $device = null;
        }

        return view('pos', [
            'posSetup' => [
                'device' => $device?->only(['id', 'name', 'code', 'store_id']),
                'can_manage_printers' => $authorization->allows(
                    $request->user(),
                    OrganizationPermission::PrintersManage,
                ),
            ],
        ]);
    }
}

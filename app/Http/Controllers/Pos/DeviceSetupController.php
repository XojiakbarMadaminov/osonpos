<?php

namespace App\Http\Controllers\Pos;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DeviceSetupController extends Controller
{
    public function __invoke(Request $request, OrganizationAuthorization $authorization): View
    {
        abort_unless(
            $authorization->allows($request->user(), OrganizationPermission::PrintersManage),
            403,
        );

        return view('pos');
    }
}

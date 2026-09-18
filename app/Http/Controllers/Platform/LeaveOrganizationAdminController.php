<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\PlatformOrganizationAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveOrganizationAdminController extends Controller
{
    public function __invoke(Request $request, PlatformOrganizationAccess $access): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin && $access->isActiveFor($request->user()), 403);

        $access->leave();

        return redirect('/platform');
    }
}

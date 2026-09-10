<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Pos\BuildPosBootstrap;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        BuildPosBootstrap $bootstrap,
        OrganizationAuthorization $authorization,
    ): JsonResponse {
        abort_unless($authorization->allows($request->user(), OrganizationPermission::PosAccess), 403);

        return response()->json(['data' => $bootstrap->execute($request->user())]);
    }
}

<?php

namespace App\Http\Controllers\Api\Pos;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Printing\QzSigningService;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SignQzPayloadRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QzSecurityController extends Controller
{
    public function certificate(
        Request $request,
        QzSigningService $signing,
        OrganizationAuthorization $authorization,
    ): Response {
        $this->authorizeSigning($request, $authorization);

        return response($signing->certificate(), 200, ['Content-Type' => 'text/plain']);
    }

    public function sign(
        SignQzPayloadRequest $request,
        QzSigningService $signing,
        OrganizationAuthorization $authorization,
    ): Response {
        $this->authorizeSigning($request, $authorization);

        return response($signing->sign($request->validated('data')), 200, ['Content-Type' => 'text/plain']);
    }

    private function authorizeSigning(Request $request, OrganizationAuthorization $authorization): void
    {
        abort_unless(config('printing.qz.signing_enabled'), 404);
        abort_unless($authorization->allows($request->user(), OrganizationPermission::PosAccess), 403);
    }
}

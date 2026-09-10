<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Devices\RegisterDevice;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\RegisterDeviceRequest;
use Illuminate\Http\JsonResponse;

class RegisterDeviceController extends Controller
{
    public function __invoke(
        RegisterDeviceRequest $request,
        RegisterDevice $registerDevice,
        OrganizationAuthorization $authorization,
    ): JsonResponse {
        abort_unless(
            $authorization->allows($request->user(), OrganizationPermission::PosAccess),
            403,
        );

        $device = $registerDevice->execute(
            $request->validated('name'),
            $request->validated('code'),
        );
        $request->session()->put('current_device_id', $device->getKey());

        return response()->json([
            'data' => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'code' => $device->code,
                'store_id' => $device->store_id,
            ],
        ], 201);
    }
}

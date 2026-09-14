<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Devices\ActivateDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ActivateDeviceRequest;
use App\Support\DeviceCredential;
use Illuminate\Http\JsonResponse;

class ActivateDeviceController extends Controller
{
    public function __invoke(
        ActivateDeviceRequest $request,
        ActivateDevice $activateDevice,
        DeviceCredential $deviceCredential,
    ): JsonResponse {
        $result = $activateDevice->execute(
            $request->user(),
            $request->validated('activation_code'),
        );
        $device = $result['device'];

        $request->session()->put([
            'current_organization_id' => $device->organization_id,
            'current_store_id' => $device->store_id,
            DeviceCredential::SESSION_KEY => $result['credential'],
        ]);

        return response()->json([
            'data' => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'code' => $device->code,
                'store_id' => $device->store_id,
            ],
        ])->withCookie($deviceCredential->cookie($result['credential']));
    }
}

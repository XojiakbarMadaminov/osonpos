<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Support\DeviceContext;
use Illuminate\Http\JsonResponse;

class CurrentDeviceController extends Controller
{
    public function __invoke(DeviceContext $context): JsonResponse
    {
        $device = $context->requireCurrent();

        return response()->json([
            'data' => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'code' => $device->code,
                'store_id' => $device->store_id,
            ],
        ]);
    }
}

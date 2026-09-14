<?php

namespace App\Actions\Devices;

use App\Models\Device;
use App\Support\DeviceActivationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateDeviceActivationCode
{
    public function __construct(private readonly DeviceActivationCode $activationCode) {}

    public function execute(Device $device): string
    {
        return DB::transaction(function () use ($device): string {
            $device = Device::query()->lockForUpdate()->findOrFail($device->getKey());

            do {
                $code = mb_strtoupper(Str::random(8));
                $hash = $this->activationCode->hash($code);
            } while (Device::query()->where('activation_code_hash', $hash)->exists());

            $device->forceFill([
                'activation_code_hash' => $hash,
                'activation_expires_at' => now()->addMinutes(10),
            ])->save();

            return $code;
        });
    }
}

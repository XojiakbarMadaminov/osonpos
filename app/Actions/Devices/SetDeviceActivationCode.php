<?php

namespace App\Actions\Devices;

use App\Models\Device;
use App\Support\DeviceActivationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetDeviceActivationCode
{
    public function __construct(private readonly DeviceActivationCode $activationCode) {}

    public function execute(Device $device, string $code): void
    {
        DB::transaction(function () use ($device, $code): void {
            $device = Device::query()->lockForUpdate()->findOrFail($device->getKey());
            $code = $this->activationCode->normalize($code);

            if (preg_match('/^[0-9]{6}$/', $code) !== 1) {
                throw ValidationException::withMessages([
                    'activation_code' => 'Aktivatsiya kodi 6 xonali raqam bo‘lishi kerak.',
                ]);
            }

            $hash = $this->activationCode->hash($code);

            if (Device::query()
                ->where('activation_code_hash', $hash)
                ->whereKeyNot($device->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'activation_code' => 'Bu aktivatsiya kodi boshqa qurilmada ishlatilmoqda.',
                ]);
            }

            $device->forceFill(['activation_code_hash' => $hash])->save();
        });
    }
}

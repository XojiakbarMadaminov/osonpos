<?php

namespace App\Support;

use App\Models\Device;
use LogicException;

class DeviceContext
{
    private ?Device $device = null;

    public function current(): ?Device
    {
        return $this->device;
    }

    public function set(Device $device): Device
    {
        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $this->device = $device;
    }

    public function requireCurrent(): Device
    {
        return $this->device
            ?? throw new LogicException('Qurilma muhiti ishga tushirilmagan.');
    }

    public function clear(): void
    {
        $this->device = null;
    }
}

<?php

namespace App\Support;

class DeviceActivationCode
{
    public function normalize(string $code): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $this->normalize($code), (string) config('app.key'));
    }
}

<?php

namespace App\Support;

use App\Models\Device;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class DeviceCredential
{
    public const COOKIE_NAME = 'osonpos_device';

    public const SESSION_KEY = 'current_device_credential';

    public function __construct(private readonly CookieJar $cookies) {}

    public function issue(Device $device): string
    {
        $secret = Str::random(64);

        $device->forceFill([
            'credential_hash' => hash('sha256', $secret),
            'activated_at' => now(),
        ])->save();

        return $device->getKey().'.'.$secret;
    }

    public function resolve(Request $request): ?Device
    {
        $credential = $request->cookie(self::COOKIE_NAME)
            ?? ($request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null);

        return is_string($credential) ? $this->resolveToken($credential) : null;
    }

    public function resolveToken(string $credential): ?Device
    {
        [$deviceId, $secret] = array_pad(explode('.', $credential, 2), 2, null);

        if (! is_string($deviceId) || ! is_string($secret) || $deviceId === '' || $secret === '') {
            return null;
        }

        return Device::query()
            ->whereKey($deviceId)
            ->where('credential_hash', hash('sha256', $secret))
            ->where('is_active', true)
            ->first();
    }

    public function cookie(string $credential): Cookie
    {
        return $this->cookies->make(
            name: self::COOKIE_NAME,
            value: $credential,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function forgetCookie(): Cookie
    {
        return $this->cookies->forget(self::COOKIE_NAME);
    }
}

<?php

namespace App\Actions\Devices;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Device;
use App\Models\User;
use App\Support\DeviceActivationCode;
use App\Support\DeviceCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateDevice
{
    public function __construct(
        private readonly DeviceActivationCode $activationCode,
        private readonly DeviceCredential $credential,
        private readonly OrganizationAuthorization $authorization,
        private readonly SubscriptionAccess $subscriptions,
    ) {}

    /** @return array{device: Device, credential: string} */
    public function execute(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code): array {
            $device = Device::query()
                ->with(['organization', 'store'])
                ->where('activation_code_hash', $this->activationCode->hash($code))
                ->lockForUpdate()
                ->first();

            if (! $device || ! $this->canActivate($user, $device)) {
                throw ValidationException::withMessages([
                    'activation_code' => 'Aktivatsiya kodi noto‘g‘ri.',
                ]);
            }

            $credential = $this->credential->issue($device);

            $device->forceFill(['last_seen_at' => now()])->save();

            return compact('device', 'credential');
        });
    }

    private function canActivate(User $user, Device $device): bool
    {
        if (! $device->is_active || ! $device->store->is_active) {
            return false;
        }

        if ($device->organization->status !== OrganizationStatus::Active) {
            return false;
        }

        if (! $user->organizations()->whereKey($device->organization_id)->exists()) {
            return false;
        }

        $hasPosAccess = $this->authorization->runForUserInTenant(
            $user,
            $device->organization,
            fn (User $tenantUser): bool => $tenantUser->can(OrganizationPermission::PosAccess->value),
        );
        $isOwner = $this->authorization->runForUserInTenant(
            $user,
            $device->organization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );
        $hasStoreAccess = $isOwner || $user->stores()->whereKey($device->store_id)->exists();

        return $hasPosAccess
            && $hasStoreAccess
            && $this->subscriptions->isActive($device->organization)
            && $this->subscriptions->hasFeature($device->organization, 'pos');
    }
}

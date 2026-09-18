<?php

namespace App\Domain\Platform;

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class PlatformOrganizationAccess
{
    public const SESSION_KEY = 'platform_organization_access_id';

    public function isActiveFor(User $user, Organization|int|null $organization = null): bool
    {
        if (! $user->is_platform_admin || ! app()->bound('session')) {
            return false;
        }

        $selectedOrganizationId = $this->organizationId();

        if (! $selectedOrganizationId) {
            return false;
        }

        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        return $organizationId === null || (int) $organizationId === $selectedOrganizationId;
    }

    public function organizationId(): ?int
    {
        $organizationId = Session::get(self::SESSION_KEY);

        return filled($organizationId) ? (int) $organizationId : null;
    }

    public function enter(User $user, Organization $organization): Store
    {
        if (! $user->is_platform_admin) {
            abort(403);
        }

        $store = $organization->stores()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $store) {
            throw ValidationException::withMessages([
                'organizationId' => 'Bu tashkilotda faol filial mavjud emas.',
            ]);
        }

        Session::put([
            self::SESSION_KEY => $organization->getKey(),
            'current_organization_id' => $organization->getKey(),
            'current_store_id' => $store->getKey(),
        ]);

        return $store;
    }

    public function leave(): void
    {
        Session::forget([
            self::SESSION_KEY,
            'current_organization_id',
            'current_store_id',
        ]);
    }
}

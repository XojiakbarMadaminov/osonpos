<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

class TenantContext
{
    private ?Organization $organization = null;

    public function resolveFor(User $user, int|string|null $preferredOrganizationId = null): Organization
    {
        $query = $user->organizations()->orderBy('organizations.id');

        $organization = $preferredOrganizationId === null
            ? $query->first()
            : $query->whereKey($preferredOrganizationId)->first();

        if (! $organization) {
            throw new AuthorizationException('Ruxsat berilgan tashkilot aniqlanmadi.');
        }

        return $this->organization = $organization;
    }

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function requireCurrent(): Organization
    {
        return $this->organization
            ?? throw new LogicException('Tashkilot muhiti ishga tushirilmagan.');
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    public function owns(object $resource): bool
    {
        return $this->organization !== null
            && isset($resource->organization_id)
            && (int) $resource->organization_id === (int) $this->organization->getKey();
    }

    public function clear(): void
    {
        $this->organization = null;
    }
}

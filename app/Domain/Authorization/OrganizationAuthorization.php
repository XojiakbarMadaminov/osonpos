<?php

namespace App\Domain\Authorization;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Spatie\Permission\PermissionRegistrar;

class OrganizationAuthorization
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function runInTenant(Organization|int $organization, Closure $callback): mixed
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        $this->permissionRegistrar->setPermissionsTeamId($organizationId);

        try {
            return $callback();
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    public function runForUserInTenant(User $user, Organization|int $organization, Closure $callback): mixed
    {
        return $this->runInTenant($organization, function () use ($user, $callback): mixed {
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $callback($user);
        });
    }

    public function allows(User $user, OrganizationPermission $permission): bool
    {
        $organization = $this->tenantContext->current();

        if (! $organization || ! $user->organizations()->whereKey($organization->getKey())->exists()) {
            return false;
        }

        return $this->runForUserInTenant(
            $user,
            $organization,
            fn (User $tenantUser): bool => $tenantUser->can($permission->value),
        );
    }

    public function ownsCurrentTenant(object $resource): bool
    {
        return $this->tenantContext->owns($resource);
    }
}

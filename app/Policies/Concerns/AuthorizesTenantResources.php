<?php

namespace App\Policies\Concerns;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Models\User;

trait AuthorizesTenantResources
{
    protected function allows(User $user, OrganizationPermission $permission, ?object $resource = null): bool
    {
        $authorization = app(OrganizationAuthorization::class);

        return $authorization->allows($user, $permission)
            && ($resource === null || $authorization->ownsCurrentTenant($resource));
    }
}

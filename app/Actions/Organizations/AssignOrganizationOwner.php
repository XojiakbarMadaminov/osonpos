<?php

namespace App\Actions\Organizations;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class AssignOrganizationOwner
{
    public function __construct(
        private readonly CreateDefaultOrganizationRoles $createDefaultRoles,
        private readonly OrganizationAuthorization $authorization,
    ) {}

    public function execute(Organization $organization, User $owner): void
    {
        $this->createDefaultRoles->execute($organization);
        $organization->users()->syncWithoutDetaching([$owner->getKey()]);

        $this->authorization->runForUserInTenant(
            $owner,
            $organization,
            fn (User $tenantOwner) => $tenantOwner->assignRole(OrganizationRole::Owner->value),
        );
    }
}

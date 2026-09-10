<?php

namespace App\Domain\Subscription;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Models\User;
use App\Support\TenantContext;

class FeaturePermissionAccess
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionAccess $subscriptions,
        private readonly OrganizationAuthorization $authorization,
    ) {}

    public function allows(User $user, string $featureCode, OrganizationPermission $permission): bool
    {
        $organization = $this->tenantContext->current();

        return $organization !== null
            && $this->subscriptions->hasFeature($organization, $featureCode)
            && $this->authorization->allows($user, $permission);
    }
}

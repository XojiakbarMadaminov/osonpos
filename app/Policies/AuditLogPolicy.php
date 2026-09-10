<?php

namespace App\Policies;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TenantContext;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        $organization = app(TenantContext::class)->current();

        return $organization && app(OrganizationAuthorization::class)->runForUserInTenant(
            $user,
            $organization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user) && $auditLog->belongsToTenant(app(TenantContext::class)->requireCurrent());
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}

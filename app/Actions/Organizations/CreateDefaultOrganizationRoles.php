<?php

namespace App\Actions\Organizations;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateDefaultOrganizationRoles
{
    public function __construct(
        private readonly OrganizationAuthorization $authorization,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function execute(Organization $organization): Collection
    {
        $permissions = collect(OrganizationPermission::cases())
            ->mapWithKeys(fn (OrganizationPermission $permission): array => [
                $permission->value => Permission::findOrCreate($permission->value, 'web'),
            ]);

        $roles = $this->authorization->runInTenant($organization, function () use ($organization, $permissions): Collection {
            return collect(OrganizationRole::cases())->mapWithKeys(function (OrganizationRole $role) use ($organization, $permissions): array {
                $model = Role::query()->firstOrCreate([
                    'organization_id' => $organization->getKey(),
                    'name' => $role->value,
                    'guard_name' => 'web',
                ]);

                $model->syncPermissions(
                    collect($role->permissions())->map(fn (OrganizationPermission $permission) => $permissions[$permission->value]),
                );

                return [$role->value => $model];
            });
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $roles;
    }
}

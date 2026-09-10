<?php

namespace Database\Seeders;

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(CreateDefaultOrganizationRoles $createDefaultRoles): void
    {
        foreach (OrganizationPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        Organization::query()->each(
            fn (Organization $organization) => $createDefaultRoles->execute($organization),
        );
    }
}

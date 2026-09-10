<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Domain\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Support\TenantContext;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Override;

class CreateRole extends CreateRecord
{
    public Collection $permissions;

    protected static string $resource = RoleResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissions = collect($data)
            ->filter(fn (mixed $permission, string $key): bool => ! in_array($key, ['name', 'guard_name', 'select_all', Utils::getTenantModelForeignKey()], true))
            ->values()
            ->flatten()
            ->unique();

        $data[Utils::getTenantModelForeignKey()] = app(TenantContext::class)->requireCurrent()->getKey();

        return Arr::only($data, ['name', 'guard_name', Utils::getTenantModelForeignKey()]);
    }

    protected function afterCreate(): void
    {
        $permissionModels = collect();
        $this->permissions->each(function (string $permission) use ($permissionModels): void {
            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                'name' => $permission,
                'guard_name' => $this->data['guard_name'],
            ]));
        });

        $this->record->syncPermissions($permissionModels);

        app(AuditLogger::class)->record(
            AuditEvent::RolePermissionsChanged,
            $this->record,
            ['permissions' => []],
            ['permissions' => $this->record->permissions()->pluck('name')->sort()->values()->all()],
            request()->user(),
            (int) app(TenantContext::class)->requireCurrent()->getKey(),
        );
    }
}

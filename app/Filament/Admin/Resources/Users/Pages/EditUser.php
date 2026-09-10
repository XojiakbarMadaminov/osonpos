<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Actions\Organizations\SaveOrganizationUser;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $member */
        $member = $this->record;
        $organization = app(TenantContext::class)->requireCurrent();

        $data['role_id'] = $member->roles()
            ->where('roles.organization_id', $organization->getKey())
            ->value('roles.id');
        $data['store_ids'] = $member->stores()
            ->where('stores.organization_id', $organization->getKey())
            ->pluck('stores.id')
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $roleId = (int) $data['role_id'];
        $storeIds = $data['store_ids'];
        unset($data['role_id'], $data['store_ids']);

        return app(SaveOrganizationUser::class)->execute(request()->user(), $record, $data, $roleId, $storeIds);
    }
}

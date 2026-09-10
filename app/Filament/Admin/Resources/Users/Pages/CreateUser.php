<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Actions\Organizations\SaveOrganizationUser;
use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roleId = (int) $data['role_id'];
        $storeIds = $data['store_ids'];
        unset($data['role_id'], $data['store_ids']);

        return app(SaveOrganizationUser::class)->execute(request()->user(), null, $data, $roleId, $storeIds);
    }
}

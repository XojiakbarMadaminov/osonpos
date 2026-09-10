<?php

namespace App\Filament\Platform\Resources\PlatformUsers\Pages;

use App\Filament\Platform\Resources\PlatformUsers\PlatformUserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlatformUser extends CreateRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = new User($data);
        $user->is_platform_admin = true;
        $user->save();

        return $user;
    }
}

<?php

namespace App\Filament\Platform\Resources\PlatformUsers\Pages;

use App\Filament\Platform\Resources\PlatformUsers\PlatformUserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPlatformUser extends EditRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $record->fill($data);
        $record->is_platform_admin = (bool) $data['is_platform_admin'];
        $record->save();

        return $record;
    }
}

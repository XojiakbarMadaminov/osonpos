<?php

namespace App\Filament\Platform\Resources\Subscriptions\Pages;

use App\Filament\Platform\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['organization_id']);

        return $data;
    }
}

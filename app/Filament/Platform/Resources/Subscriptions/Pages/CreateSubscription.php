<?php

namespace App\Filament\Platform\Resources\Subscriptions\Pages;

use App\Filament\Platform\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = Arr::pull($data, 'organization_id');
        $subscription = new Subscription($data);
        $subscription->organization()->associate($organizationId);
        $subscription->save();

        return $subscription;
    }
}

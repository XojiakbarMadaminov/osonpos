<?php

namespace App\Filament\Platform\Resources\Features\Pages;

use App\Filament\Platform\Resources\Features\FeatureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeature extends CreateRecord
{
    protected static string $resource = FeatureResource::class;
}

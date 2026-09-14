<?php

namespace App\Filament\Platform\Resources\Organizations\Pages;

use App\Filament\Platform\Pages\OnboardOrganization;
use App\Filament\Platform\Resources\Organizations\OrganizationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('onboard')
                ->label('Tashkilot qo‘shish')
                ->url(OnboardOrganization::getUrl()),
        ];
    }
}

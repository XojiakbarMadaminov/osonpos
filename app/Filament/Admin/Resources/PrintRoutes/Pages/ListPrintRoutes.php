<?php

namespace App\Filament\Admin\Resources\PrintRoutes\Pages;

use App\Filament\Admin\Resources\PrintRoutes\PrintRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrintRoutes extends ListRecords
{
    protected static string $resource = PrintRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

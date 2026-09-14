<?php

namespace App\Filament\Admin\Resources\Shifts\Pages;

use App\Filament\Admin\Resources\Shifts\ShiftResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [ShiftResource::closeAction()];
    }
}

<?php

namespace App\Filament\Admin\Resources\Shifts\Pages;

use App\Filament\Admin\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;
}

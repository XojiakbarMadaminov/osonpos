<?php

namespace App\Filament\Admin\Resources\Tables\Pages;

use App\Filament\Admin\Resources\Tables\TableResource;
use Filament\Resources\Pages\EditRecord;

class EditTable extends EditRecord
{
    protected static string $resource = TableResource::class;
}

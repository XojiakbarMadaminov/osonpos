<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Organization;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class OrganizationAccessWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.platform.widgets.organization-access-widget';

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, Organization> */
    public function organizations(): Collection
    {
        return Organization::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }
}

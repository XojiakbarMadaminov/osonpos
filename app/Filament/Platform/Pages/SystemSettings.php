<?php

namespace App\Filament\Platform\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SystemSettings extends Page
{
    protected string $view = 'filament.platform.pages.system-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Tizim ma’lumotlari';

    protected static ?string $title = 'Tizim ma’lumotlari';

    public function supportDetails(): array
    {
        return [
            'Ilova manzili' => config('app.url'),
            'Muhit' => app()->environment(),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Kesh' => config('cache.default'),
            'Navbat' => config('queue.default'),
            'Ma’lumotlar bazasi' => config('database.default'),
        ];
    }
}

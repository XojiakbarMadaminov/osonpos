<?php

namespace App\Filament\Platform\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SystemSettings extends Page
{
    protected string $view = 'filament.platform.pages.system-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    public function supportDetails(): array
    {
        return [
            'Application URL' => config('app.url'),
            'Environment' => app()->environment(),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Cache' => config('cache.default'),
            'Queue' => config('queue.default'),
            'Database' => config('database.default'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Pages;

use App\Support\TenantContext;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        $user = request()->user();
        $organization = app(TenantContext::class)->current();

        if (! $user || ! $organization) {
            return false;
        }

        return $user->organizations()->whereKey($organization)->exists();
    }
}

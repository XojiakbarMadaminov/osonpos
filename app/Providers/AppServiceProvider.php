<?php

namespace App\Providers;

use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(StoreContext::class);
        $this->app->scoped(DeviceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TextInput::configureUsing(fn (TextInput $component) => $component->translateLabel());
        Select::configureUsing(fn (Select $component) => $component->translateLabel());
        Toggle::configureUsing(fn (Toggle $component) => $component->translateLabel());
        DatePicker::configureUsing(fn (DatePicker $component) => $component->translateLabel());
        DateTimePicker::configureUsing(fn (DateTimePicker $component) => $component->translateLabel());
        TextColumn::configureUsing(fn (TextColumn $component) => $component->translateLabel());
        IconColumn::configureUsing(fn (IconColumn $component) => $component->translateLabel());
        TextEntry::configureUsing(fn (TextEntry $component) => $component->translateLabel());

        RateLimiter::for('pos', fn (Request $request): Limit => Limit::perMinute(180)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('qz-signing', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('device-activation', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->user()?->getAuthIdentifier().'|'.$request->ip()));
    }
}

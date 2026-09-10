<?php

namespace App\Providers;

use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subscription;
use App\Observers\CriticalConfigurationObserver;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
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
        RateLimiter::for('pos', fn (Request $request): Limit => Limit::perMinute(180)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('qz-signing', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        Product::observe(CriticalConfigurationObserver::class);
        Printer::observe(CriticalConfigurationObserver::class);
        PrintRoute::observe(CriticalConfigurationObserver::class);
        Store::observe(CriticalConfigurationObserver::class);
        Subscription::observe(CriticalConfigurationObserver::class);
    }
}

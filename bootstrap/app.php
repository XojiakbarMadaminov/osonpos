<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsurePosAuthenticated;
use App\Http\Middleware\EnsurePosDeviceIsActivated;
use App\Http\Middleware\InitializeDeviceContext;
use App\Http\Middleware\InitializeStoreContext;
use App\Http\Middleware\InitializeTenantContext;
use App\Http\Middleware\RestoreDeviceSessionContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (): string => route('filament.admin.auth.login'));
        $middleware->alias([
            'subscription.active' => EnsureActiveSubscription::class,
            'context.store' => InitializeStoreContext::class,
            'context.tenant' => InitializeTenantContext::class,
            'context.device' => InitializeDeviceContext::class,
            'pos.auth' => EnsurePosAuthenticated::class,
            'pos.device.ready' => EnsurePosDeviceIsActivated::class,
            'pos.restore-device' => RestoreDeviceSessionContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/pos/*')) {
                return response()->json([
                    'message' => 'POS’dan foydalanish uchun avval tizimga kiring.',
                ], 401);
            }
        });
    })->create();

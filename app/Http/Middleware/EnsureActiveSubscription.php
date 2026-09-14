<?php

namespace App\Http\Middleware;

use App\Domain\Subscription\SubscriptionAccess;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionAccess $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $this->subscriptions->isActive($this->tenantContext->requireCurrent()),
            403,
            'Yangi POS amallari uchun faol obuna talab qilinadi.',
        );

        return $next($request);
    }
}

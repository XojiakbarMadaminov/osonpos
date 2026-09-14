<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());
            $request->session()->flash('pos_login_required', true);

            return redirect()->route('filament.admin.auth.login');
        }

        return $next($request);
    }
}

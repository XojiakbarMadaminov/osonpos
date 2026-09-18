<?php

namespace App\Http\Controllers\Pos;

use App\Domain\Shift\CurrentShift;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request, CurrentShift $currentShift): RedirectResponse
    {
        if ($currentShift->for($request->user())) {
            return redirect('/pos')->with(
                'pos_logout_error',
                'Akkauntdan chiqishdan oldin joriy smenani yoping.',
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', url('/pos'));
        $request->session()->flash('pos_logout_success', true);

        return redirect()->route('filament.admin.auth.login');
    }
}

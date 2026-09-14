<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectAdminHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $target = Filament::getCurrentOrDefaultPanel()->getRedirectUrl();

        if (blank($target) || rtrim($target, '/') === rtrim($request->url(), '/')) {
            $target = route('pos');
        }

        return redirect()->to($target);
    }
}

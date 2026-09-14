<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SwitchAdminContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SwitchAdminContextRequest;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;

class SwitchAdminContextController extends Controller
{
    public function __invoke(
        SwitchAdminContextRequest $request,
        SwitchAdminContext $switchContext,
    ): RedirectResponse {
        $switchContext->execute($request->user(), $request->integer('store_id'));

        Notification::make()
            ->success()
            ->title('Faol filial yangilandi')
            ->send();

        return redirect('/admin');
    }
}

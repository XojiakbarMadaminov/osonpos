<?php

use App\Http\Controllers\Admin\SwitchAdminContextController;
use App\Http\Controllers\Pos\DeviceSetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::redirect('/panel', '/admin');

Route::post('/admin/context', SwitchAdminContextController::class)
    ->middleware('auth')
    ->name('admin.context.switch');

Route::view('/pos', 'pos')
    ->middleware(['pos.auth', 'pos.restore-device', 'context.tenant', 'context.store', 'pos.device.ready'])
    ->name('pos');
Route::get('/pos/device-setup', DeviceSetupController::class)
    ->middleware(['pos.auth', 'pos.restore-device', 'context.tenant', 'context.store'])
    ->name('pos.device-setup');

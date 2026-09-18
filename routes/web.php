<?php

use App\Http\Controllers\Admin\SwitchAdminContextController;
use App\Http\Controllers\Platform\EnterOrganizationAdminController;
use App\Http\Controllers\Platform\LeaveOrganizationAdminController;
use App\Http\Controllers\Pos\DeviceSetupController;
use App\Http\Controllers\Pos\LogoutController as PosLogoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::redirect('/panel', '/admin');

Route::post('/admin/context', SwitchAdminContextController::class)
    ->middleware('auth')
    ->name('admin.context.switch');

Route::post('/platform/organization-access', EnterOrganizationAdminController::class)
    ->middleware('auth')
    ->name('platform.organization-access.enter');

Route::post('/admin/platform-access/leave', LeaveOrganizationAdminController::class)
    ->middleware('auth')
    ->name('platform.organization-access.leave');

Route::view('/pos', 'pos')
    ->middleware(['pos.auth', 'pos.restore-device', 'context.tenant', 'context.store', 'pos.device.ready'])
    ->name('pos');
Route::get('/pos/device-setup', DeviceSetupController::class)
    ->middleware(['pos.auth', 'pos.restore-device', 'context.tenant', 'context.store'])
    ->name('pos.device-setup');
Route::post('/pos/logout', PosLogoutController::class)
    ->middleware(['pos.auth', 'pos.restore-device', 'context.tenant', 'context.store', 'pos.device.ready'])
    ->name('pos.logout');

<?php

use App\Http\Controllers\Pos\DeviceSetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::view('/pos', 'pos')->name('pos');
Route::get('/pos/device-setup', DeviceSetupController::class)
    ->middleware(['auth', 'context.tenant', 'context.store'])
    ->name('pos.device-setup');

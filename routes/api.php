<?php

use App\Http\Controllers\Api\Pos\ActivateDeviceController;
use App\Http\Controllers\Api\Pos\BootstrapController;
use App\Http\Controllers\Api\Pos\CancelOrderController;
use App\Http\Controllers\Api\Pos\CompleteOrderController;
use App\Http\Controllers\Api\Pos\CreateMixedPaymentController;
use App\Http\Controllers\Api\Pos\CreatePaymentController;
use App\Http\Controllers\Api\Pos\CurrentDeviceController;
use App\Http\Controllers\Api\Pos\CustomerController;
use App\Http\Controllers\Api\Pos\CustomerLookupController;
use App\Http\Controllers\Api\Pos\CustomerReceiptController;
use App\Http\Controllers\Api\Pos\KitchenPrintController;
use App\Http\Controllers\Api\Pos\KitchenRemovalPrintController;
use App\Http\Controllers\Api\Pos\MoveOrderTableController;
use App\Http\Controllers\Api\Pos\OrderController;
use App\Http\Controllers\Api\Pos\OrderCustomerController;
use App\Http\Controllers\Api\Pos\OrderDiscountController;
use App\Http\Controllers\Api\Pos\OrderItemController;
use App\Http\Controllers\Api\Pos\OrderItemRemovalController;
use App\Http\Controllers\Api\Pos\PrinterController;
use App\Http\Controllers\Api\Pos\QzSecurityController;
use App\Http\Controllers\Api\Pos\ShiftController;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('pos')
    ->middleware([StartSession::class, 'auth:sanctum', 'throttle:pos'])
    ->group(function (): void {
        Route::post('/devices/activate', ActivateDeviceController::class)
            ->middleware('throttle:device-activation')
            ->name('api.pos.devices.activate');

        Route::middleware(['pos.restore-device', 'context.tenant', 'context.store'])->group(function (): void {
            Route::get('/device', CurrentDeviceController::class)
                ->middleware('context.device');
            Route::middleware('context.device')->group(function (): void {
                Route::get('/bootstrap', BootstrapController::class);
                Route::get('/customers/lookup', CustomerLookupController::class);
                Route::get('/printers', [PrinterController::class, 'index']);
                Route::put('/printers/{printer}/binding', [PrinterController::class, 'bind']);
                Route::get('/qz/certificate', [QzSecurityController::class, 'certificate']);
                Route::post('/qz/sign', [QzSecurityController::class, 'sign'])->middleware('throttle:qz-signing');

                Route::middleware('subscription.active')->group(function (): void {
                    Route::post('/customers', [CustomerController::class, 'store']);
                    Route::get('/shifts/current', [ShiftController::class, 'current']);
                    Route::post('/shifts', [ShiftController::class, 'store']);
                    Route::post('/shifts/{shift}/close', [ShiftController::class, 'close']);
                    Route::get('/orders', [OrderController::class, 'index']);
                    Route::post('/orders', [OrderController::class, 'store']);
                    Route::get('/orders/{order}', [OrderController::class, 'show']);
                    Route::patch('/orders/{order}', [OrderController::class, 'update']);
                    Route::patch('/orders/{order}/table', MoveOrderTableController::class);
                    Route::put('/orders/{order}/customer', [OrderCustomerController::class, 'update']);
                    Route::delete('/orders/{order}/customer', [OrderCustomerController::class, 'destroy']);
                    Route::put('/orders/{order}/discount', [OrderDiscountController::class, 'update']);
                    Route::delete('/orders/{order}/discount', [OrderDiscountController::class, 'destroy']);
                    Route::post('/orders/{order}/items', OrderItemController::class);
                    Route::post('/orders/{order}/item-removals', OrderItemRemovalController::class);
                    Route::post('/orders/{order}/payments', CreatePaymentController::class);
                    Route::post('/orders/{order}/mixed-payments', CreateMixedPaymentController::class);
                    Route::post('/orders/{order}/complete', CompleteOrderController::class);
                    Route::post('/orders/{order}/receipt', [CustomerReceiptController::class, 'prepare']);
                    Route::post('/orders/{order}/receipt/reprint', [CustomerReceiptController::class, 'reprint']);
                    Route::post('/orders/{order}/send-kitchen', [KitchenPrintController::class, 'prepare']);
                    Route::post('/orders/{order}/send-kitchen/confirm', [KitchenPrintController::class, 'confirm']);
                    Route::post('/orders/{order}/send-kitchen/reprint', [KitchenPrintController::class, 'reprint']);
                    Route::post('/orders/{order}/send-kitchen-removals', [KitchenRemovalPrintController::class, 'prepare']);
                    Route::post('/orders/{order}/send-kitchen-removals/confirm', [KitchenRemovalPrintController::class, 'confirm']);
                    Route::post('/orders/{order}/send-kitchen-removals/reprint', [KitchenRemovalPrintController::class, 'reprint']);
                    Route::post('/orders/{order}/cancel', CancelOrderController::class);
                });
            });
        });
    });

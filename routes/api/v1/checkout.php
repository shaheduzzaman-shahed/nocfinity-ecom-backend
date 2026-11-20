<?php

use App\Http\Controllers\Api\V1\Customer\CheckoutController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'preview']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/orders/{id}/confirmation', [OrderController::class, 'confirmation']);
});

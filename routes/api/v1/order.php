<?php

use App\Http\Controllers\Api\V1\Customer\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
});

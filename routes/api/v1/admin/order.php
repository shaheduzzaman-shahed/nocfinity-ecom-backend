<?php

use App\Http\Controllers\Api\V1\Admin\OrderController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff']], function () {
    Route::group(['middleware' => 'permission:orders.view'], function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });

    Route::group(['middleware' => 'permission:orders.update_status'], function () {
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
});

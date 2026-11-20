<?php

use App\Http\Controllers\Api\V1\Admin\CustomerController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff', 'permission:customers.view']], function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::get('/customers/{id}/orders', [CustomerController::class, 'orders']);
});

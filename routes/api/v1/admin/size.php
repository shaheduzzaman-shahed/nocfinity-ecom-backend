<?php

use App\Http\Controllers\Api\V1\Admin\SizeController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff']], function () {
    Route::group(['middleware' => 'permission:products.view'], function () {
        Route::get('/sizes', [SizeController::class, 'index']);
    });

    Route::group(['middleware' => 'permission:products.create'], function () {
        Route::post('/sizes', [SizeController::class, 'store']);
    });

    Route::group(['middleware' => 'permission:products.edit'], function () {
        Route::put('/sizes/{size}', [SizeController::class, 'update']);
    });

    Route::group(['middleware' => 'permission:products.delete'], function () {
        Route::delete('/sizes/{size}', [SizeController::class, 'destroy']);
    });
});

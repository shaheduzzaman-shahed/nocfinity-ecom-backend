<?php

use App\Http\Controllers\Api\V1\Admin\ColorController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff']], function () {
    Route::group(['middleware' => 'permission:products.view'], function () {
        Route::get('/colors', [ColorController::class, 'index']);
    });

    Route::group(['middleware' => 'permission:products.create'], function () {
        Route::post('/colors', [ColorController::class, 'store']);
    });

    Route::group(['middleware' => 'permission:products.edit'], function () {
        Route::put('/colors/{color}', [ColorController::class, 'update']);
    });

    Route::group(['middleware' => 'permission:products.delete'], function () {
        Route::delete('/colors/{color}', [ColorController::class, 'destroy']);
    });
});

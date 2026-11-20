<?php

use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\ProductImageController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff']], function () {
    Route::group(['middleware' => 'permission:products.view'], function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product');
    });

    Route::group(['middleware' => 'permission:products.create'], function () {
        Route::post('/products', [ProductController::class, 'store']);
    });

    Route::group(['middleware' => 'permission:products.edit'], function () {
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->whereNumber('product');
        Route::post('/products/{product}/images', [ProductImageController::class, 'store'])
            ->whereNumber('product');
        Route::put('/products/{product}/images/{image}', [ProductImageController::class, 'update'])
            ->whereNumber('product')
            ->whereNumber('image');
        Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->whereNumber('product')
            ->whereNumber('image');
    });

    Route::group(['middleware' => 'permission:products.delete'], function () {
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->whereNumber('product');
    });
});

<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'time' => now()->toDateTimeString()]);
})->middleware('throttle:10,1');

Route::prefix('v1')->group(function () {
    // Customer & public routes
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/category.php';
    require __DIR__.'/api/v1/product.php';

    // Admin routes
    require __DIR__.'/api/v1/admin/index.php';
});

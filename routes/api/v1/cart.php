<?php

use App\Http\Controllers\Api\V1\Cart\CartController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::put('/cart/items/{id}', [CartController::class, 'update'])
        ->whereNumber('id');
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy'])
        ->whereNumber('id');
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/merge', [CartController::class, 'merge']);
});

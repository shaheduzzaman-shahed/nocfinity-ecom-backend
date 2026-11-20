<?php

use App\Http\Controllers\Api\V1\Customer\ProductReviewController;
use Illuminate\Support\Facades\Route;

// Public review listing for a product
Route::get('/products/{product}/reviews', [ProductReviewController::class, 'index'])
    ->whereNumber('product');

// Authenticated customer review actions
Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::post('/products/{product}/reviews', [ProductReviewController::class, 'store'])
        ->whereNumber('product');
    Route::put('/reviews/{review}', [ProductReviewController::class, 'update'])
        ->whereNumber('review');
    Route::delete('/reviews/{review}', [ProductReviewController::class, 'destroy'])
        ->whereNumber('review');
});

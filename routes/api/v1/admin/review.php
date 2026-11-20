<?php

use App\Http\Controllers\Api\V1\Admin\ProductReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('reviews')->middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::get('/', [ProductReviewController::class, 'index'])
        ->middleware('permission:reviews.view');

    Route::get('/{id}', [ProductReviewController::class, 'show'])
        ->middleware('permission:reviews.view');

    Route::patch('/{id}/status', [ProductReviewController::class, 'updateStatus'])
        ->middleware('permission:reviews.manage');

    Route::delete('/{id}', [ProductReviewController::class, 'destroy'])
        ->middleware('permission:reviews.delete');
});

<?php

use App\Http\Controllers\Api\V1\Coupon\CouponController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::post('/coupons/apply', [CouponController::class, 'apply']);
    Route::delete('/coupons/remove', [CouponController::class, 'remove']);
});

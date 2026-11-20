<?php

use App\Http\Controllers\Api\V1\Admin\CouponController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff']], function () {
    Route::group(['middleware' => 'permission:coupons.view'], function () {
        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupons/{coupon}', [CouponController::class, 'show'])
            ->whereNumber('coupon');
    });

    Route::group(['middleware' => 'permission:coupons.manage'], function () {
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::put('/coupons/{coupon}', [CouponController::class, 'update'])
            ->whereNumber('coupon');
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])
            ->whereNumber('coupon');
    });
});

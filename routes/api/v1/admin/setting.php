<?php

use App\Http\Controllers\Api\V1\Admin\Settings\CurrencySettingController;
use App\Http\Controllers\Api\V1\Admin\Settings\ShippingSettingController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff']], function () {
    Route::get('/settings/shipping', [ShippingSettingController::class, 'show'])
        ->middleware('permission:shipping.view');

    Route::put('/settings/shipping', [ShippingSettingController::class, 'update'])
        ->middleware('permission:shipping.manage');

    Route::get('/settings/currency', [CurrencySettingController::class, 'show'])
        ->middleware('permission:currency.view');

    Route::put('/settings/currency', [CurrencySettingController::class, 'update'])
        ->middleware('permission:currency.manage');
});

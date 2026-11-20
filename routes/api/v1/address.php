<?php

use App\Http\Controllers\Api\V1\Customer\ShippingAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::get('/addresses', [ShippingAddressController::class, 'index']);
    Route::post('/addresses', [ShippingAddressController::class, 'store']);
    Route::get('/addresses/{id}', [ShippingAddressController::class, 'show'])->whereNumber('id');
    Route::put('/addresses/{id}', [ShippingAddressController::class, 'update'])->whereNumber('id');
    Route::delete('/addresses/{id}', [ShippingAddressController::class, 'destroy'])->whereNumber('id');
    Route::match(['put', 'patch'], '/addresses/{id}/default', [ShippingAddressController::class, 'setDefault'])->whereNumber('id');

    // Convenient aliases
    Route::get('/shipping-addresses', [ShippingAddressController::class, 'index']);
    Route::post('/shipping-addresses', [ShippingAddressController::class, 'store']);
    Route::get('/shipping-addresses/{id}', [ShippingAddressController::class, 'show'])->whereNumber('id');
    Route::put('/shipping-addresses/{id}', [ShippingAddressController::class, 'update'])->whereNumber('id');
    Route::delete('/shipping-addresses/{id}', [ShippingAddressController::class, 'destroy'])->whereNumber('id');
    Route::match(['put', 'patch'], '/shipping-addresses/{id}/default', [ShippingAddressController::class, 'setDefault'])->whereNumber('id');
});

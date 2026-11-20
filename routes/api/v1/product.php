<?php

use App\Http\Controllers\Api\V1\Catalog\FeaturedProductController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show'])
    ->whereNumber('product');
Route::get('/featured-products', [FeaturedProductController::class, 'index']);

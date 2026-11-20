<?php

use App\Http\Controllers\Api\V1\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff', 'permission:dashboard.view|reports.view']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/sales', [DashboardController::class, 'sales']);
    Route::get('/dashboard/recent-orders', [DashboardController::class, 'recentOrders']);
});

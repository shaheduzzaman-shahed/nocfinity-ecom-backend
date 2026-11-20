<?php

use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', 'staff', 'permission:activity_logs.view']], function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/actions', [ActivityLogController::class, 'actions']);
    Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);
});

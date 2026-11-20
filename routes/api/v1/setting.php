<?php

use App\Http\Controllers\Api\V1\Settings\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/settings/currency', [SettingController::class, 'currency']);

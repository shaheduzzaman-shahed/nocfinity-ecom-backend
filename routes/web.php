<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('db-refresh', function () {
    Artisan::call('migrate:fresh', [
        '--force' => true,
    ]);
    Artisan::call('db:seed', [
        '--force' => true,
    ]);

    return 'Database refreshed and seeded successfully.';
});

Route::get('storage-link', function () {
    Artisan::call('storage:link', [
        '--force' => true,
    ]);
    
    return 'Storage linked successfully.';
});

use Illuminate\Support\Facades\Storage;


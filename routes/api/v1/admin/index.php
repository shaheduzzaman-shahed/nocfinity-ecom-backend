<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    include __DIR__.'/auth.php';
    include __DIR__.'/role.php';
    include __DIR__.'/category.php';
    include __DIR__.'/color.php';
    include __DIR__.'/size.php';
});

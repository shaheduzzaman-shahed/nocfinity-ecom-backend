<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Super Admin role is granted every ability regardless of the
        // permissions explicitly attached to it.
        Gate::before(function (User $user) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}

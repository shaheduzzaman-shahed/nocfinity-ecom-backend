<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Configure general API rate limiter (60 requests per minute by authenticated user or IP)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // The Super Admin role is granted every ability regardless of the
        // permissions explicitly attached to it.
        Gate::before(function (User $user) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Configure default password reset URL to point to Next.js frontend
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? $notifiable->getEmailForPasswordReset()
                : ($notifiable->email ?? '');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($email);
        });

        // Configure branded transactional email template for password reset
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? $notifiable->getEmailForPasswordReset()
                : ($notifiable->email ?? '');

            $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($email);
            $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

            return (new MailMessage)
                ->subject('Reset Your Password - Nocfinity')
                ->view('emails.auth.reset-password', [
                    'user' => $notifiable,
                    'resetUrl' => $resetUrl,
                    'frontendUrl' => $frontendUrl,
                    'expireMinutes' => $expireMinutes,
                ]);
        });
    }
}

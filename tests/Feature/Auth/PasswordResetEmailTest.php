<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('forgot password sends reset password notification with custom branded mail and frontend url', function () {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response = $this->postJson('/api/v1/forgot-password', [
        'email' => 'john@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'If an account exists with that email, a password reset link has been sent.',
        ]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Reset Your Password - Nocfinity');

        $html = (string) $mail->render();

        expect($html)
            ->toContain('Reset Your Password')
            ->toContain('john@example.com')
            ->toContain('John Doe')
            ->toContain('/reset-password?token=')
            ->toContain('email=john%40example.com')
            ->toContain('Reset Password')
            ->toContain('Important Notice:')
            ->toContain('minutes');

        return true;
    });
});

test('reset password notification mail view renders accurately', function () {
    $user = User::factory()->create([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
    ]);

    $notification = new ResetPassword('test-token-12345');
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Reset Your Password - Nocfinity');

    $html = (string) $mail->render();

    expect($html)
        ->toContain('/reset-password?token=test-token-12345')
        ->toContain('email=alice%40example.com')
        ->toContain('Alice Smith')
        ->toContain('alice@example.com')
        ->toContain('60 minutes');
});

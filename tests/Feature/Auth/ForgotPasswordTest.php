<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('forgot password sends reset link for existing email', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'If an account exists with that email, a password reset link has been sent.',
        ]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password returns same response for non-existent email', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'If an account exists with that email, a password reset link has been sent.',
        ]);

    Notification::assertNothingSent();
});

test('forgot password fails with missing email', function () {
    $response = $this->postJson('/api/v1/forgot-password', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('forgot password fails with invalid email', function () {
    $response = $this->postJson('/api/v1/forgot-password', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

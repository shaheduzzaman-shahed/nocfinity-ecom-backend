<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('user can reset password with valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ]);

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
});

test('password reset revokes all tokens', function () {
    $user = User::factory()->create();
    $user->createToken('auth-token');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('password reset fails with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('password reset fails with weak password', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('password reset fails with missing fields', function () {
    $response = $this->postJson('/api/v1/reset-password', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token', 'email', 'password']);
});

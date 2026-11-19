<?php

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('login:127.0.0.1');
});

test('customer can login with valid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'account_type' => AccountType::Customer,
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'account_type'],
                'token',
            ],
        ])
        ->assertJson([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'account_type' => 'customer',
                ],
            ],
        ]);
});

test('login fails with wrong password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'WrongPassword1!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('login fails with non-existent email', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'nobody@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('login uses generic error message for wrong email and wrong password', function () {
    User::factory()->create([
        'email' => 'exists@example.com',
        'password' => bcrypt('Password1!'),
    ]);

    $wrongEmail = $this->postJson('/api/v1/login', [
        'email' => 'wrong@example.com',
        'password' => 'Password1!',
    ]);

    $wrongPassword = $this->postJson('/api/v1/login', [
        'email' => 'exists@example.com',
        'password' => 'WrongPassword1!',
    ]);

    expect($wrongEmail->json('message'))->toBe($wrongPassword->json('message'));
});

test('login fails for inactive user', function () {
    $user = User::factory()->inactive()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Your account has been deactivated.',
        ]);
});

test('login fails for staff account on customer login endpoint', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('login is rate limited after five failed attempts', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'WrongPassword1!',
        ]);
    }

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.email.0'))->toContain('Too many login attempts');
});

test('login revokes previous tokens', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'account_type' => AccountType::Customer,
    ]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('login fails with missing fields', function () {
    $response = $this->postJson('/api/v1/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

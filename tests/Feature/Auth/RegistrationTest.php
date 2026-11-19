<?php

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can register with valid data', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(201)
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
            'message' => 'Registration successful.',
            'data' => [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'account_type' => 'customer',
                ],
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'account_type' => AccountType::Customer->value,
    ]);
});

test('registration returns a valid sanctum token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $token = $response->json('data.token');

    $this->getJson('/api/v1/register', [
        'Authorization' => "Bearer {$token}",
    ]);

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('registration fails with missing name', function () {
    $response = $this->postJson('/api/v1/register', [
        'email' => 'john@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('registration fails with invalid email', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'not-an-email',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails with weak password', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration fails with mismatched password confirmation', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'DifferentPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration response has correct json structure', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
    expect($response->json('data.user.id'))->toBeInt();
    expect($response->json('data.user.account_type'))->toBe('customer');
});

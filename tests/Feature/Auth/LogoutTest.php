<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/logout');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token');

    $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/logout');

    $response->assertOk();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson('/api/v1/logout');

    $response->assertStatus(401);
});

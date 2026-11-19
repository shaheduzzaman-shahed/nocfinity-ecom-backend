<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can get their profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/profile');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'email', 'phone', 'avatar', 'account_type', 'is_active'],
        ])
        ->assertJson([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'account_type' => 'customer',
            ],
        ]);
});

test('profile does not expose sensitive fields', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/profile');

    $data = $response->json('data');
    expect($data)->not->toHaveKey('password');
    expect($data)->not->toHaveKey('remember_token');
});

test('unauthenticated user cannot get profile', function () {
    $response = $this->getJson('/api/v1/profile');

    $response->assertStatus(401);
});

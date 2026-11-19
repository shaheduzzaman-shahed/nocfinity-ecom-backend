<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('active customer can access protected routes', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/profile');

    $response->assertOk();
});

test('staff user cannot access customer routes', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/profile');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Forbidden.',
        ]);
});

test('inactive customer cannot access protected routes', function () {
    $user = User::factory()->inactive()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/profile');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Your account has been deactivated.',
        ]);
});

test('unauthenticated user cannot access protected routes', function () {
    $response = $this->getJson('/api/v1/profile');

    $response->assertStatus(401);
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('active staff can access protected admin routes', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/profile')->assertOk();
});

test('customer cannot access protected admin routes', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/admin/profile');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Forbidden.',
        ]);
});

test('customer token cannot access protected admin routes', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $token = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/admin/profile')
        ->assertStatus(403);
});

test('staff token cannot access customer routes', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $token = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/profile')
        ->assertStatus(403);
});

test('inactive staff cannot access protected admin routes', function () {
    $user = User::factory()->staff()->inactive()->create();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/profile');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Your account has been deactivated.',
        ]);
});

test('staff token without administrative ability is rejected', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['customer']);

    $response = $this->getJson('/api/v1/admin/profile');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'This token is not authorized for administrative access.',
        ]);
});

test('unauthenticated user cannot access protected admin routes', function () {
    $this->getJson('/api/v1/admin/profile')->assertStatus(401);
});

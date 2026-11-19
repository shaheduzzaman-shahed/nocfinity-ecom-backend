<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('customer can update their name', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'name' => 'Updated Name',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => ['name' => 'Updated Name'],
        ]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
});

test('customer can update their email', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'email' => 'newemail@example.com',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'newemail@example.com']);
});

test('customer can update their phone', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'phone' => '+9876543210',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '+9876543210']);
});

test('customer can change password with current password', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'current_password' => 'OldPassword1!',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertOk();

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
});

test('password change requires current password', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('password change fails with wrong current password', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'current_password' => 'WrongPassword1!',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('email update fails with duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('customer can keep same email without unique violation', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile', [
        'email' => $user->email,
        'name' => 'Same Email New Name',
    ]);

    $response->assertOk();
});

test('unauthenticated user cannot update profile', function () {
    $response = $this->putJson('/api/v1/profile', ['name' => 'Test']);

    $response->assertStatus(401);
});

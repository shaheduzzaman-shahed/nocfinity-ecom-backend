<?php

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('staff can retrieve their profile', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/profile');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'phone', 'account_type', 'is_active'],
                'permissions',
            ],
        ])
        ->assertJson([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'account_type' => AccountType::Staff->value,
                ],
            ],
        ]);
});

test('staff profile never exposes sensitive fields', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/profile');

    $response->assertOk();

    expect($response->json('data.user'))
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

test('staff can update their name', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson('/api/v1/admin/profile', [
        'name' => 'Updated Staff Name',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => ['name' => 'Updated Staff Name'],
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Staff Name',
    ]);
});

test('staff cannot change their email through profile management', function () {
    $user = User::factory()->staff()->create();
    $originalEmail = $user->email;
    Sanctum::actingAs($user, ['admin']);

    $this->putJson('/api/v1/admin/profile', [
        'name' => 'Renamed Staff',
        'email' => 'hijacked@example.com',
        'account_type' => AccountType::Customer->value,
        'is_active' => false,
    ])->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Renamed Staff',
        'email' => $originalEmail,
        'account_type' => AccountType::Staff->value,
        'is_active' => true,
    ]);
});

test('staff profile update requires a valid name', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson('/api/v1/admin/profile', [
        'name' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('staff can change their password with the current password', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('OldPassword1!'),
    ]);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson('/api/v1/admin/profile/password', [
        'current_password' => 'OldPassword1!',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
});

test('staff password change requires the current password', function () {
    $user = User::factory()->staff()->create();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson('/api/v1/admin/profile/password', [
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('staff password change fails with the wrong current password', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('OldPassword1!'),
    ]);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson('/api/v1/admin/profile/password', [
        'current_password' => 'WrongPassword1!',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password'])
        ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
});

test('staff password change enforces the password policy', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('OldPassword1!'),
    ]);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson('/api/v1/admin/profile/password', [
        'current_password' => 'OldPassword1!',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('staff password change revokes other sessions but keeps the current one', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('OldPassword1!'),
    ]);

    $token = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'OldPassword1!',
    ])->json('data.token');

    $user->createToken('other-session', ['admin']);

    expect($user->tokens()->count())->toBe(2);

    $this->withToken($token)
        ->putJson('/api/v1/admin/profile/password', [
            'current_password' => 'OldPassword1!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertOk();

    $remaining = $user->tokens()->pluck('name');

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first())->toBe('admin-token');

    $this->withToken($token)
        ->getJson('/api/v1/admin/profile')
        ->assertOk();
});

test('customer cannot access the staff profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/profile')->assertStatus(403);
    $this->putJson('/api/v1/admin/profile', ['name' => 'Nope'])->assertStatus(403);
    $this->putJson('/api/v1/admin/profile/password', [
        'current_password' => 'password',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertStatus(403);
});

test('unauthenticated user cannot access the staff profile', function () {
    $this->getJson('/api/v1/admin/profile')->assertStatus(401);
});

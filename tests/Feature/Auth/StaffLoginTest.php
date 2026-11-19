<?php

use App\Enums\AccountType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('admin-login:127.0.0.1');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('staff can login with valid credentials', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'account_type'],
                'permissions',
                'token',
            ],
        ])
        ->assertJson([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'account_type' => AccountType::Staff->value,
                ],
            ],
        ]);

    expect($response->json('data.permissions'))->toBeArray();
});

test('staff login issues an administrative token', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->assertOk();

    $token = $user->tokens()->first();

    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('admin-token')
        ->and($token->can('admin'))->toBeTrue();
});

test('staff login returns effective permissions from assigned roles', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $role = Role::create([
        'name' => 'product-manager',
        'display_name' => 'Product Manager',
    ]);

    Permission::create(['name' => 'products.view', 'module' => 'products']);
    Permission::create(['name' => 'products.create', 'module' => 'products']);

    $role->syncPermissions(['products.view', 'products.create']);
    $user->assignRole($role);

    $response = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertOk();

    expect($response->json('data.permissions'))
        ->toBe(['products.create', 'products.view']);
});

test('staff login fails with wrong password', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'WrongPassword1!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('staff login fails with non-existent email', function () {
    $response = $this->postJson('/api/v1/admin/login', [
        'email' => 'nobody@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('customer account cannot login through the admin endpoint', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'account_type' => AccountType::Customer,
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('staff login fails for inactive staff account', function () {
    $user = User::factory()->staff()->inactive()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Your account has been deactivated.',
        ]);
});

test('staff login reveals nothing about the account type on bad credentials', function () {
    $customer = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => bcrypt('Password1!'),
    ]);

    $wrongEmail = $this->postJson('/api/v1/admin/login', [
        'email' => 'missing@example.com',
        'password' => 'Password1!',
    ]);

    $wrongPassword = $this->postJson('/api/v1/admin/login', [
        'email' => $customer->email,
        'password' => 'WrongPassword1!',
    ]);

    expect($wrongEmail->json('message'))->toBe($wrongPassword->json('message'));
});

test('staff login is rate limited after five failed attempts', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/admin/login', [
            'email' => $user->email,
            'password' => 'WrongPassword1!',
        ]);
    }

    $response = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.email.0'))->toContain('Too many login attempts');
});

test('admin rate limit is separate from the customer login rate limit', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    RateLimiter::hit('login:127.0.0.1', 60);
    RateLimiter::hit('login:127.0.0.1', 60);
    RateLimiter::hit('login:127.0.0.1', 60);
    RateLimiter::hit('login:127.0.0.1', 60);
    RateLimiter::hit('login:127.0.0.1', 60);

    $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->assertOk();

    RateLimiter::clear('login:127.0.0.1');
});

test('staff login revokes previous tokens', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('staff login fails with missing fields', function () {
    $response = $this->postJson('/api/v1/admin/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('staff can logout and the token is revoked', function () {
    $user = User::factory()->staff()->create([
        'password' => bcrypt('Password1!'),
    ]);

    $token = $this->postJson('/api/v1/admin/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->json('data.token');

    $this->withToken($token)
        ->postJson('/api/v1/admin/logout')
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Logged out successfully.']);

    expect($user->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/admin/profile')
        ->assertStatus(401);
});

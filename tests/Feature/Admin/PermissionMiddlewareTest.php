<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('grants access only to endpoints covered by the effective permissions', function () {
    $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
    Permission::create(['name' => 'roles.view', 'module' => 'roles']);
    Permission::create(['name' => 'roles.manage', 'module' => 'roles']);
    $role->syncPermissions(['roles.view']);

    $user = User::factory()->staff()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/roles')->assertOk();
    $this->postJson('/api/v1/admin/roles', ['display_name' => 'Blocked'])->assertStatus(403);
});

test('permissions from multiple assigned roles are combined', function () {
    Permission::create(['name' => 'roles.view', 'module' => 'roles']);
    Permission::create(['name' => 'roles.manage', 'module' => 'roles']);
    Permission::create(['name' => 'staff.manage', 'module' => 'staff']);

    $viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
    $viewerRole->syncPermissions(['roles.view']);

    $managerRole = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
    $managerRole->syncPermissions(['roles.manage']);

    $user = User::factory()->staff()->create();
    $user->assignRole($viewerRole, $managerRole);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/roles')->assertOk();
    $this->postJson('/api/v1/admin/roles', ['display_name' => 'Combined Access'])
        ->assertCreated();
});

test('super admin bypasses permission middleware', function () {
    $user = superAdminUser();
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/roles')->assertOk();
    $this->getJson('/api/v1/admin/permissions')->assertOk();
    $this->getJson('/api/v1/admin/staff')->assertOk();
    $this->postJson('/api/v1/admin/roles', ['display_name' => 'Super Admin Created'])
        ->assertCreated();
});

test('super admin profile reports every permission', function () {
    $this->seed(PermissionSeeder::class);
    $user = superAdminUser();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/profile');

    $response->assertOk()
        ->assertJsonPath('data.roles', ['super-admin']);

    expect($response->json('data.permissions'))
        ->toHaveCount(Permission::count())
        ->toContain('dashboard.view')
        ->toContain('staff.manage');
});

test('customer tokens never satisfy a permission protected endpoint', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/roles')->assertStatus(403);
});

test('permission failures use the standard API error envelope', function () {
    $user = staffWithPermissions(['products.view']);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/roles');

    $response->assertStatus(403)
        ->assertJsonStructure(['success', 'message'])
        ->assertJson(['success' => false]);
});

<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('staff with roles.view can list roles', function () {
    $user = staffWithPermissions(['roles.view']);
    $role = Role::create(['name' => 'support', 'display_name' => 'Support']);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/roles');

    $response->assertOk()
        ->assertJson(['success' => true, 'message' => 'Roles retrieved successfully.'])
        ->assertJsonFragment([
            'name' => 'support',
            'display_name' => 'Support',
            'is_system' => false,
        ]);

    expect($response->json('data'))->toHaveCount(2)
        ->and($role->fresh())->not->toBeNull();
});

test('roles listing requires the roles.view permission', function () {
    $user = staffWithPermissions(['products.view']);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/roles');

    $response->assertStatus(403)
        ->assertJson(['success' => false]);
});

test('super admin can list roles without explicit permissions', function () {
    $user = superAdminUser();
    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/roles');

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'super-admin',
            'is_system' => true,
        ]);
});

test('staff with roles.view can view a single role', function () {
    $user = staffWithPermissions(['roles.view']);
    $role = Role::create(['name' => 'order-manager', 'display_name' => 'Order Manager']);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson("/api/v1/admin/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'order-manager');
});

test('viewing a missing role returns 404', function () {
    $user = staffWithPermissions(['roles.view']);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/roles/999')
        ->assertStatus(404)
        ->assertJson(['success' => false]);
});

test('staff with roles.manage can create a role with permissions', function () {
    $user = staffWithPermissions(['roles.manage']);
    $permissions = ['products.view', 'reviews.manage'];

    foreach ($permissions as $name) {
        Permission::create(['name' => $name, 'module' => explode('.', $name)[0]]);
    }

    Sanctum::actingAs($user, ['admin']);

    $response = $this->postJson('/api/v1/admin/roles', [
        'display_name' => 'Content Editor',
        'description' => 'Manages catalog content.',
        'permissions' => $permissions,
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => [
                'name' => 'content-editor',
                'display_name' => 'Content Editor',
                'permissions' => ['products.view', 'reviews.manage'],
            ],
        ]);

    $this->assertDatabaseHas('roles', [
        'name' => 'content-editor',
        'display_name' => 'Content Editor',
        'is_system' => false,
    ]);

    $this->assertDatabaseHas('role_has_permissions', [
        'role_id' => $response->json('data.id'),
    ]);
});

test('role names are generated uniquely from the display name', function () {
    $user = staffWithPermissions(['roles.manage']);
    Sanctum::actingAs($user, ['admin']);

    $first = $this->postJson('/api/v1/admin/roles', ['display_name' => 'Editor']);
    $second = $this->postJson('/api/v1/admin/roles', ['display_name' => 'Editor']);

    $first->assertCreated()->assertJsonPath('data.name', 'editor');
    $second->assertCreated()->assertJsonPath('data.name', 'editor-2');
});

test('creating a role validates the display name and permissions', function () {
    $user = staffWithPermissions(['roles.manage']);
    Sanctum::actingAs($user, ['admin']);

    $response = $this->postJson('/api/v1/admin/roles', [
        'permissions' => ['does.not.exist'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['display_name', 'permissions.0']);
});

test('staff with roles.manage can update a role and replace its permissions', function () {
    $user = staffWithPermissions(['roles.manage']);
    $role = Role::create(['name' => 'custom-role', 'display_name' => 'Custom Role']);

    foreach (['products.view', 'products.create', 'reviews.view'] as $name) {
        Permission::create(['name' => $name, 'module' => explode('.', $name)[0]]);
    }

    $role->syncPermissions(['products.view', 'products.create']);

    Sanctum::actingAs($user, ['admin']);

    $response = $this->putJson("/api/v1/admin/roles/{$role->id}", [
        'display_name' => 'Renamed Role',
        'permissions' => ['products.view', 'reviews.view'],
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Role updated successfully.',
            'data' => [
                'display_name' => 'Renamed Role',
                'permissions' => ['products.view', 'reviews.view'],
            ],
        ]);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['products.view', 'reviews.view']);
});

test('the system super admin role cannot be updated', function () {
    $user = staffWithPermissions(['roles.manage']);
    $systemRole = Role::create([
        'name' => 'super-admin',
        'display_name' => 'Super Admin',
        'is_system' => true,
    ]);
    Sanctum::actingAs($user, ['admin']);

    $this->putJson("/api/v1/admin/roles/{$systemRole->id}", [
        'display_name' => 'Hacked',
    ])->assertStatus(403);

    $this->assertDatabaseHas('roles', [
        'id' => $systemRole->id,
        'display_name' => 'Super Admin',
    ]);
});

test('staff with roles.manage can delete an unused role', function () {
    $user = staffWithPermissions(['roles.manage']);
    $role = Role::create(['name' => 'temporary-role', 'display_name' => 'Temporary']);
    Sanctum::actingAs($user, ['admin']);

    $this->deleteJson("/api/v1/admin/roles/{$role->id}")
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Role deleted successfully.']);

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('the system super admin role cannot be deleted', function () {
    $user = staffWithPermissions(['roles.manage']);
    $systemRole = Role::create([
        'name' => 'super-admin',
        'display_name' => 'Super Admin',
        'is_system' => true,
    ]);
    Sanctum::actingAs($user, ['admin']);

    $this->deleteJson("/api/v1/admin/roles/{$systemRole->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
});

test('a role assigned to staff cannot be deleted', function () {
    $user = staffWithPermissions(['roles.manage']);
    $role = Role::create(['name' => 'assigned-role', 'display_name' => 'Assigned']);

    $staff = User::factory()->staff()->create();
    $staff->assignRole($role);

    Sanctum::actingAs($user, ['admin']);

    $this->deleteJson("/api/v1/admin/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'message' => 'This role is assigned to staff members and cannot be deleted.',
        ]);

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});

test('staff with roles.view can list permissions grouped with module metadata', function () {
    $user = staffWithPermissions(['roles.view']);
    Permission::create(['name' => 'dashboard.view', 'module' => 'dashboard']);
    Permission::create(['name' => 'products.view', 'module' => 'products']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Sanctum::actingAs($user, ['admin']);

    $response = $this->getJson('/api/v1/admin/permissions');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [['id', 'name', 'module', 'description']],
        ])
        ->assertJsonFragment(['name' => 'dashboard.view', 'module' => 'dashboard'])
        ->assertJsonFragment(['name' => 'products.view', 'module' => 'products']);
});

test('listing permissions requires the roles.view permission', function () {
    $user = staffWithPermissions(['products.view']);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/permissions')->assertStatus(403);
});

test('customers cannot access role management', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/roles')->assertStatus(403);
    $this->postJson('/api/v1/admin/roles', ['display_name' => 'Nope'])->assertStatus(403);
});

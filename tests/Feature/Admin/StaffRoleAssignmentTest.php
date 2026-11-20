<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('staff with staff.manage can list staff members with their roles', function () {
    $admin = staffWithPermissions(['staff.manage']);
    $support = Role::create(['name' => 'support', 'display_name' => 'Support']);

    $staff = User::factory()->staff()->create(['name' => 'Support Agent']);
    $staff->assignRole($support);

    User::factory()->create(['name' => 'A Customer']);

    Sanctum::actingAs($admin, ['admin']);

    $response = $this->getJson('/api/v1/admin/staff');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [['id', 'name', 'email', 'is_active', 'roles']],
        ]);

    $emails = collect($response->json('data'))->pluck('email');

    expect($emails)->toContain($admin->email, $staff->email)
        ->not->toContain(User::where('name', 'A Customer')->first()->email);

    $response->assertJsonFragment([
        'name' => 'Support Agent',
        'roles' => ['support'],
    ]);
});

test('staff roles can be assigned and replaced', function () {
    $admin = staffWithPermissions(['staff.manage']);
    $support = Role::create(['name' => 'support', 'display_name' => 'Support']);
    $orderManager = Role::create(['name' => 'order-manager', 'display_name' => 'Order Manager']);

    $staff = User::factory()->staff()->create();
    $staff->assignRole($support);

    Sanctum::actingAs($admin, ['admin']);

    $response = $this->putJson("/api/v1/admin/staff/{$staff->id}/roles", [
        'roles' => ['order-manager'],
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Staff roles updated successfully.',
            'data' => ['roles' => ['order-manager']],
        ]);

    expect($staff->fresh()->getRoleNames()->all())->toBe(['order-manager']);
});

test('assigning an empty role array removes all roles', function () {
    $admin = staffWithPermissions(['staff.manage']);
    $support = Role::create(['name' => 'support', 'display_name' => 'Support']);

    $staff = User::factory()->staff()->create();
    $staff->assignRole($support);

    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/v1/admin/staff/{$staff->id}/roles", ['roles' => []])
        ->assertOk();

    expect($staff->fresh()->getRoleNames()->all())->toBe([]);
});

test('the super admin role cannot be assigned through role management', function () {
    $admin = staffWithPermissions(['staff.manage']);
    Role::create(['name' => 'super-admin', 'display_name' => 'Super Admin', 'is_system' => true]);

    $staff = User::factory()->staff()->create();

    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/v1/admin/staff/{$staff->id}/roles", [
        'roles' => ['super-admin'],
    ])->assertStatus(403);

    expect($staff->fresh()->getRoleNames()->all())->toBe([]);
});

test('the super admin account cannot have its roles modified', function () {
    $admin = staffWithPermissions(['staff.manage']);
    $systemRole = Role::create([
        'name' => 'super-admin',
        'display_name' => 'Super Admin',
        'is_system' => true,
    ]);

    $superAdmin = User::factory()->staff()->create();
    $superAdmin->assignRole($systemRole);

    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/v1/admin/staff/{$superAdmin->id}/roles", [
        'roles' => [],
    ])->assertStatus(403);

    expect($superAdmin->fresh()->getRoleNames()->all())->toBe(['super-admin']);
});

test('staff cannot change their own role assignments', function () {
    $admin = staffWithPermissions(['staff.manage']);
    $support = Role::create(['name' => 'support', 'display_name' => 'Support']);

    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/v1/admin/staff/{$admin->id}/roles", [
        'roles' => ['support'],
    ])->assertStatus(403);

    expect($admin->fresh()->getRoleNames()->all())->not->toBe(['support']);
});

test('customer accounts cannot be targeted by staff role assignment', function () {
    $admin = staffWithPermissions(['staff.manage']);
    Role::create(['name' => 'support', 'display_name' => 'Support']);

    $customer = User::factory()->create();

    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/v1/admin/staff/{$customer->id}/roles", [
        'roles' => ['support'],
    ])->assertStatus(404);

    expect($customer->fresh()->getRoleNames()->all())->toBe([]);
});

test('staff role assignment validates role names', function () {
    $admin = staffWithPermissions(['staff.manage']);
    $staff = User::factory()->staff()->create();

    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/v1/admin/staff/{$staff->id}/roles", [
        'roles' => ['not-a-role'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['roles.0']);
});

test('staff role assignment requires the staff.manage permission', function () {
    $admin = staffWithPermissions(['roles.manage']);
    $staff = User::factory()->staff()->create();

    Sanctum::actingAs($admin, ['admin']);

    $this->getJson('/api/v1/admin/staff')->assertStatus(403);
    $this->putJson("/api/v1/admin/staff/{$staff->id}/roles", ['roles' => []])
        ->assertStatus(403);
});

<?php

use App\Enums\AccountType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('allows super admin to list all staff accounts', function () {
    $superAdmin = superAdminUser();
    User::factory()->staff()->count(3)->create();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/staff');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Staff retrieved successfully.',
        ]);

    // 1 super admin + 3 created staff = 4
    expect(count($response->json('data')))->toBe(4);
});

it('allows staff with staff.manage permission to list staff accounts', function () {
    $staff = staffWithPermissions(['staff.manage']);
    User::factory()->staff()->count(2)->create();

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/staff');
    $response->assertOk();
    expect(count($response->json('data')))->toBe(3);
});

it('denies staff without staff.manage permission from staff listing', function () {
    $staff = staffWithPermissions(['orders.view']);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/staff');
    $response->assertForbidden();
});

it('allows creating a new staff account with roles', function () {
    $superAdmin = superAdminUser();
    $role = Role::where('name', 'product-manager')->first();

    Sanctum::actingAs($superAdmin, ['admin']);

    $payload = [
        'name' => 'Marcus Vance',
        'email' => 'marcus@fashion.com',
        'password' => 'SecurePass123!',
        'phone' => '+15550001111',
        'roles' => [$role->name],
        'is_active' => true,
    ];

    $response = $this->postJson('/api/v1/admin/staff', $payload);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Staff member created successfully.',
            'data' => [
                'name' => 'Marcus Vance',
                'email' => 'marcus@fashion.com',
                'phone' => '+15550001111',
                'is_active' => true,
                'roles' => [$role->name],
            ],
        ]);

    $created = User::where('email', 'marcus@fashion.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->account_type)->toBe(AccountType::Staff)
        ->and(Hash::check('SecurePass123!', $created->password))->toBeTrue()
        ->and($created->hasRole('product-manager'))->toBeTrue();
});

it('prevents assigning the super-admin role when creating staff', function () {
    $superAdmin = superAdminUser();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->postJson('/api/v1/admin/staff', [
        'name' => 'Intruder',
        'email' => 'intruder@fashion.com',
        'password' => 'Password123!',
        'roles' => ['super-admin'],
    ]);

    $response->assertForbidden();
});

it('enforces email uniqueness when creating staff', function () {
    $superAdmin = superAdminUser();
    $existing = User::factory()->staff()->create(['email' => 'existing@fashion.com']);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->postJson('/api/v1/admin/staff', [
        'name' => 'Duplicate',
        'email' => 'existing@fashion.com',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('allows viewing staff details', function () {
    $staff = staffWithPermissions(['staff.manage']);
    $targetStaff = User::factory()->staff()->create([
        'name' => 'Helena Troy',
        'email' => 'helena@fashion.com',
    ]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson("/api/v1/admin/staff/{$targetStaff->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $targetStaff->id,
                'name' => 'Helena Troy',
                'email' => 'helena@fashion.com',
            ],
        ]);
});

it('allows updating a staff account details and roles', function () {
    $superAdmin = superAdminUser();
    $targetStaff = User::factory()->staff()->create([
        'name' => 'Old Name',
        'email' => 'old@fashion.com',
    ]);
    $role = Role::where('name', 'order-manager')->first();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->putJson("/api/v1/admin/staff/{$targetStaff->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@fashion.com',
        'phone' => '+15559998888',
        'roles' => [$role->name],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Updated Name',
                'email' => 'updated@fashion.com',
                'phone' => '+15559998888',
                'roles' => [$role->name],
            ],
        ]);

    expect($targetStaff->fresh()->name)->toBe('Updated Name')
        ->and($targetStaff->fresh()->hasRole('order-manager'))->toBeTrue();
});

it('prohibits other staff from editing the super-admin account', function () {
    $superAdmin = superAdminUser();
    $staffManager = staffWithPermissions(['staff.manage']);

    Sanctum::actingAs($staffManager, ['admin']);

    $response = $this->putJson("/api/v1/admin/staff/{$superAdmin->id}", [
        'name' => 'Tampered Name',
    ]);

    $response->assertForbidden();
    expect($superAdmin->fresh()->name)->not->toBe('Tampered Name');
});

it('prohibits deactivating the super-admin account', function () {
    $superAdmin = superAdminUser();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->patchJson("/api/v1/admin/staff/{$superAdmin->id}/status", [
        'is_active' => false,
    ]);

    $response->assertForbidden();
    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

it('prohibits staff from deactivating their own account', function () {
    $staffManager = staffWithPermissions(['staff.manage']);

    Sanctum::actingAs($staffManager, ['admin']);

    $response = $this->patchJson("/api/v1/admin/staff/{$staffManager->id}/status", [
        'is_active' => false,
    ]);

    $response->assertForbidden();
    expect($staffManager->fresh()->is_active)->toBeTrue();
});

it('allows toggling active status on other staff members', function () {
    $superAdmin = superAdminUser();
    $targetStaff = User::factory()->staff()->create(['is_active' => true]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->patchJson("/api/v1/admin/staff/{$targetStaff->id}/status", [
        'is_active' => false,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $targetStaff->id,
                'is_active' => false,
            ],
        ]);

    expect($targetStaff->fresh()->is_active)->toBeFalse();
});

it('prohibits deleting the super-admin account', function () {
    $superAdmin = superAdminUser();
    $staffManager = staffWithPermissions(['staff.manage']);

    Sanctum::actingAs($staffManager, ['admin']);

    $response = $this->deleteJson("/api/v1/admin/staff/{$superAdmin->id}");
    $response->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
});

it('prohibits staff from deleting their own account', function () {
    $staffManager = staffWithPermissions(['staff.manage']);

    Sanctum::actingAs($staffManager, ['admin']);

    $response = $this->deleteJson("/api/v1/admin/staff/{$staffManager->id}");
    $response->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $staffManager->id]);
});

it('allows deleting a staff member account', function () {
    $superAdmin = superAdminUser();
    $targetStaff = User::factory()->staff()->create();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->deleteJson("/api/v1/admin/staff/{$targetStaff->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Staff member deleted successfully.',
        ]);

    $this->assertDatabaseMissing('users', ['id' => $targetStaff->id]);
});

<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('all documented permissions are seeded', function () {
    expect(Permission::count())->toBe(23)
        ->and(Permission::pluck('name')->all())
        ->toContain(
            'dashboard.view',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.manage',
            'orders.view', 'orders.update_status',
            'coupons.view', 'coupons.manage',
            'customers.view',
            'reports.view',
            'roles.view', 'roles.manage',
            'staff.manage',
            'shipping.view', 'shipping.manage',
            'currency.view', 'currency.manage',
            'reviews.view', 'reviews.manage', 'reviews.delete',
        );
});

test('the five default roles exist', function () {
    expect(Role::pluck('name')->all())
        ->toContain('super-admin', 'admin', 'product-manager', 'order-manager', 'support')
        ->toHaveCount(5);
});

test('super admin is the only system protected role', function () {
    $systemRoles = Role::where('is_system', true)->pluck('name')->all();

    expect($systemRoles)->toBe(['super-admin']);
});

test('the default role permission matrix matches the specification', function () {
    $matrix = Role::with('permissions')
        ->get()
        ->mapWithKeys(fn (Role $role) => [
            $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
        ]);

    expect($matrix['super-admin'])->toBe([]);

    expect($matrix['admin'])->toBe(
        Permission::orderBy('name')->pluck('name')->all()
    );

    expect($matrix['product-manager'])->toBe([
        'categories.manage',
        'categories.view',
        'products.create',
        'products.delete',
        'products.edit',
        'products.view',
    ]);

    expect($matrix['order-manager'])->toBe([
        'orders.update_status',
        'orders.view',
        'shipping.manage',
        'shipping.view',
    ]);

    expect($matrix['support'])->toBe([
        'customers.view',
        'orders.view',
    ]);
});

test('re-seeding removes permissions that are no longer part of a role', function () {
    $productManager = Role::where('name', 'product-manager')->firstOrFail();
    $productManager->givePermissionTo('reviews.manage');

    expect($productManager->fresh()->permissions->pluck('name'))->toContain('reviews.manage');

    $this->seed(RoleSeeder::class);

    expect($productManager->fresh()->permissions->pluck('name'))
        ->not->toContain('reviews.manage');
});

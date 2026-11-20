<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super-admin',
                'display_name' => 'Super Admin',
                'description' => 'Full administrative access with all permissions.',
                'is_system' => true,
                'permissions' => [], // Gets all permissions via the gate
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Broad operational access including staff and role management.',
                'is_system' => false,
                'permissions' => Permission::pluck('name')->toArray(),
            ],
            [
                'name' => 'product-manager',
                'display_name' => 'Product Manager',
                'description' => 'Product and category management.',
                'is_system' => false,
                'permissions' => [
                    'products.view', 'products.create', 'products.edit', 'products.delete',
                    'categories.view', 'categories.manage',
                ],
            ],
            [
                'name' => 'order-manager',
                'display_name' => 'Order Manager',
                'description' => 'Order management, fulfillment, and shipping configuration.',
                'is_system' => false,
                'permissions' => [
                    'orders.view', 'orders.update_status',
                    'shipping.view', 'shipping.manage',
                ],
            ],
            [
                'name' => 'support',
                'display_name' => 'Support',
                'description' => 'Read-only customer and order support access.',
                'is_system' => false,
                'permissions' => [
                    'orders.view',
                    'customers.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData,
            );

            // Always sync so re-seeding removes permissions that are no longer granted.
            $role->syncPermissions($permissions);
        }
    }
}

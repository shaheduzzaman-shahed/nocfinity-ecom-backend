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
                'permissions' => [], // Gets all permissions via gate
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Administrative access according to assigned permissions.',
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
                    'reviews.view', 'reviews.manage', 'reviews.delete',
                ],
            ],
            [
                'name' => 'order-manager',
                'display_name' => 'Order Manager',
                'description' => 'Order management and fulfillment.',
                'is_system' => false,
                'permissions' => [
                    'orders.view', 'orders.update_status',
                    'customers.view',
                    'coupons.view',
                    'shipping.view',
                ],
            ],
            [
                'name' => 'support',
                'display_name' => 'Support',
                'description' => 'Customer and order-support operations.',
                'is_system' => false,
                'permissions' => [
                    'orders.view',
                    'customers.view',
                    'products.view',
                    'reviews.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData,
            );

            if (! empty($permissions)) {
                $role->syncPermissions($permissions);
            }
        }
    }
}

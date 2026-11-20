<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'module' => 'dashboard', 'description' => 'View the admin dashboard'],

            // Products
            ['name' => 'products.view', 'module' => 'products', 'description' => 'View products'],
            ['name' => 'products.create', 'module' => 'products', 'description' => 'Create products'],
            ['name' => 'products.edit', 'module' => 'products', 'description' => 'Edit products'],
            ['name' => 'products.delete', 'module' => 'products', 'description' => 'Delete products'],

            // Categories
            ['name' => 'categories.view', 'module' => 'categories', 'description' => 'View categories'],
            ['name' => 'categories.manage', 'module' => 'categories', 'description' => 'Create, edit, and delete categories'],

            // Orders
            ['name' => 'orders.view', 'module' => 'orders', 'description' => 'View orders'],
            ['name' => 'orders.update_status', 'module' => 'orders', 'description' => 'Update order status'],

            // Coupons
            ['name' => 'coupons.view', 'module' => 'coupons', 'description' => 'View coupons'],
            ['name' => 'coupons.manage', 'module' => 'coupons', 'description' => 'Create, edit, and delete coupons'],

            // Customers
            ['name' => 'customers.view', 'module' => 'customers', 'description' => 'View customer accounts'],

            // Reports
            ['name' => 'reports.view', 'module' => 'reports', 'description' => 'View dashboard reports'],

            // Roles
            ['name' => 'roles.view', 'module' => 'roles', 'description' => 'View roles'],
            ['name' => 'roles.manage', 'module' => 'roles', 'description' => 'Create, edit, and delete roles'],

            // Staff
            ['name' => 'staff.manage', 'module' => 'staff', 'description' => 'Manage staff accounts'],

            // Shipping
            ['name' => 'shipping.view', 'module' => 'shipping', 'description' => 'View shipping configuration'],
            ['name' => 'shipping.manage', 'module' => 'shipping', 'description' => 'Edit shipping configuration'],

            // Currency
            ['name' => 'currency.view', 'module' => 'currency', 'description' => 'View currency configuration'],
            ['name' => 'currency.manage', 'module' => 'currency', 'description' => 'Edit currency configuration'],

            // Reviews
            ['name' => 'reviews.view', 'module' => 'reviews', 'description' => 'View product reviews'],
            ['name' => 'reviews.manage', 'module' => 'reviews', 'description' => 'Approve or reject reviews'],
            ['name' => 'reviews.delete', 'module' => 'reviews', 'description' => 'Delete reviews'],

            // Activity Logs / Audit
            ['name' => 'activity_logs.view', 'module' => 'system', 'description' => 'View activity and audit logs'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission,
            );
        }
    }
}

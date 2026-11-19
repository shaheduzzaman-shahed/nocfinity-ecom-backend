<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@nocfinity.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'phone' => '+1234567890',
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole('super-admin');
    }
}

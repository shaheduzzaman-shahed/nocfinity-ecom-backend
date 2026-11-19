<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 50.00,
                'usage_limit' => 100,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(6),
                'is_active' => true,
            ],
            [
                'code' => 'SAVE20',
                'type' => 'fixed',
                'value' => 20.00,
                'min_order_amount' => 100.00,
                'usage_limit' => 50,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'SUMMER25',
                'type' => 'percentage',
                'value' => 25.00,
                'min_order_amount' => 75.00,
                'usage_limit' => null,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(2),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::firstOrCreate(['code' => $coupon['code']], $coupon);
        }
    }
}

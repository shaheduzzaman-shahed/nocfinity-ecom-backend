<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_key' => 'shipping_fee', 'setting_value' => '5.00', 'group_name' => 'shipping'],
            ['setting_key' => 'shipping_enabled', 'setting_value' => 'true', 'group_name' => 'shipping'],
            ['setting_key' => 'store_currency', 'setting_value' => 'USD', 'group_name' => 'currency'],
        ];

        foreach ($settings as $setting) {
            AppSetting::firstOrCreate(
                ['setting_key' => $setting['setting_key']],
                $setting,
            );
        }
    }
}

<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

class ShippingSettingService
{
    /**
     * @return array{shipping_fee: float, shipping_enabled: bool}
     */
    public function getShippingSettings(): array
    {
        $feeSetting = AppSetting::query()
            ->where('setting_key', 'shipping_fee')
            ->first();

        $enabledSetting = AppSetting::query()
            ->where('setting_key', 'shipping_enabled')
            ->first();

        return [
            'shipping_fee' => (float) ($feeSetting?->setting_value ?? 5.00),
            'shipping_enabled' => filter_var(
                $enabledSetting?->setting_value ?? true,
                FILTER_VALIDATE_BOOLEAN,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{shipping_fee: float, shipping_enabled: bool}
     */
    public function updateShippingSettings(array $data): array
    {
        return DB::transaction(function () use ($data) {
            if (array_key_exists('shipping_fee', $data)) {
                $feeValue = number_format((float) $data['shipping_fee'], 2, '.', '');
                AppSetting::query()->updateOrCreate(
                    ['setting_key' => 'shipping_fee'],
                    [
                        'setting_value' => $feeValue,
                        'group_name' => 'shipping',
                    ],
                );
            }

            if (array_key_exists('shipping_enabled', $data)) {
                $enabledValue = filter_var($data['shipping_enabled'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                AppSetting::query()->updateOrCreate(
                    ['setting_key' => 'shipping_enabled'],
                    [
                        'setting_value' => $enabledValue,
                        'group_name' => 'shipping',
                    ],
                );
            }

            return $this->getShippingSettings();
        });
    }
}

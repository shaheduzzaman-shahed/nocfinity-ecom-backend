<?php

namespace App\Services\Settings;

use App\Events\Settings\CurrencySettingUpdated;
use App\Models\AppSetting;
use App\Support\StripeCurrencies;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    public function getStoreCurrency(): string
    {
        $currency = (string) (
            AppSetting::query()
                ->where('setting_key', 'store_currency')
                ->value('setting_value') ?? 'USD'
        );

        return strtoupper(trim($currency));
    }

    public function updateStoreCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        if (! StripeCurrencies::isSupported($normalized)) {
            throw ValidationException::withMessages([
                'currency' => 'The selected currency is not supported by Stripe.',
            ]);
        }

        AppSetting::query()->updateOrCreate(
            ['setting_key' => 'store_currency'],
            [
                'setting_value' => $normalized,
                'group_name' => 'currency',
            ],
        );

        CurrencySettingUpdated::dispatch($normalized);

        return $normalized;
    }

    /**
     * @return array<int, array{code: string, name: string, symbol: string}>
     */
    public function getSupportedCurrencies(): array
    {
        return StripeCurrencies::list();
    }
}

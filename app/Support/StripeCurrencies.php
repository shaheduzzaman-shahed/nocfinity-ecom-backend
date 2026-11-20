<?php

namespace App\Support;

class StripeCurrencies
{
    /**
     * Common presentment currencies supported by Stripe for card payments.
     *
     * @var array<string, array{name: string, symbol: string}>
     */
    protected static array $currencies = [
        'USD' => ['name' => 'United States Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound Sterling', 'symbol' => '£'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CA$'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
        'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'MX$'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
        'AED' => ['name' => 'United Arab Emirates Dirham', 'symbol' => 'AED'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
        'TWD' => ['name' => 'New Taiwan Dollar', 'symbol' => 'NT$'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft'],
        'ILS' => ['name' => 'Israeli New Shekel', 'symbol' => '₪'],
        'CLP' => ['name' => 'Chilean Peso', 'symbol' => 'CLP$'],
        'COP' => ['name' => 'Colombian Peso', 'symbol' => 'COL$'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'E£'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => '₨'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'QR'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KD'],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => 'RO'],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => 'BD'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei'],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв'],
        'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => 'AR$'],
    ];

    /**
     * @return array<string, array{name: string, symbol: string}>
     */
    public static function all(): array
    {
        return static::$currencies;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(static::$currencies);
    }

    public static function isSupported(string $code): bool
    {
        return array_key_exists(strtoupper(trim($code)), static::$currencies);
    }

    public static function isZeroDecimal(string $currency): bool
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper(trim($currency)), $zeroDecimalCurrencies, true);
    }

    public static function toStripeAmount(float $amount, string $currency): int
    {
        if (static::isZeroDecimal($currency)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    /**
     * @return array<int, array{code: string, name: string, symbol: string}>
     */
    public static function list(): array
    {
        $list = [];
        foreach (static::$currencies as $code => $info) {
            $list[] = [
                'code' => $code,
                'name' => $info['name'],
                'symbol' => $info['symbol'],
            ];
        }

        return $list;
    }
}

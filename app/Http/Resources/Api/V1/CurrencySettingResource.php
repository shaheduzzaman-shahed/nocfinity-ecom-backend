<?php

namespace App\Http\Resources\Api\V1;

use App\Support\StripeCurrencies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = is_array($this->resource)
            ? ($this->resource['currency'] ?? 'USD')
            : (string) $this->resource;

        return [
            'currency' => $currency,
            'supported_currencies' => StripeCurrencies::list(),
        ];
    }
}

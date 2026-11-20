<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shipping_fee' => (float) ($this->resource['shipping_fee'] ?? 5.00),
            'shipping_enabled' => (bool) ($this->resource['shipping_enabled'] ?? true),
        ];
    }
}

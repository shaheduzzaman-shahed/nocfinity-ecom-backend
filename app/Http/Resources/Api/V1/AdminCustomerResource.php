<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ShippingAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $addresses = null;
        if ($this->relationLoaded('shippingAddresses')) {
            $addresses = $this->shippingAddresses->map(function (ShippingAddress $addr) {
                return [
                    'id' => $addr->id,
                    'name' => $addr->name,
                    'email' => $addr->email,
                    'phone' => $addr->phone,
                    'address' => $addr->address,
                    'city' => $addr->city,
                    'state' => $addr->state,
                    'postal_code' => $addr->postal_code,
                    'country' => $addr->country,
                    'is_default' => (bool) $addr->is_default,
                ];
            });
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'is_active' => (bool) $this->is_active,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_spent' => round((float) ($this->total_spent ?? 0), 2),
            'shipping_addresses' => $addresses,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

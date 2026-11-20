<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'order_status' => is_object($this->order_status) ? $this->order_status->value : $this->order_status,
            'payment_status' => is_object($this->payment_status) ? $this->payment_status->value : $this->payment_status,
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'product_discount' => (float) $this->product_discount,
            'net_subtotal' => round(max(0.00, (float) $this->subtotal - (float) $this->product_discount), 2),
            'coupon_discount' => (float) $this->coupon_discount,
            'shipping_charge' => (float) $this->shipping_charge,
            'final_total' => (float) $this->final_total,
            'shipping_address' => [
                'id' => $this->shipping_address_id,
                'name' => $this->ship_name,
                'email' => $this->ship_email,
                'phone' => $this->ship_phone,
                'address' => $this->ship_address,
                'city' => $this->ship_city,
                'state' => $this->ship_state,
                'postal_code' => $this->ship_postal_code,
                'country' => $this->ship_country,
            ],
            'coupon' => $this->whenLoaded('coupon', function () {
                if (! $this->coupon) {
                    return null;
                }

                return [
                    'id' => $this->coupon->id,
                    'code' => $this->coupon->code,
                    'type' => $this->coupon->type,
                    'value' => (float) $this->coupon->value,
                ];
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'paid_at' => $this->paid_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

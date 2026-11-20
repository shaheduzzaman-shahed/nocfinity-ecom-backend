<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AppSetting;
use App\Models\CartItem;
use App\Services\Settings\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @var array<int, string>
     */
    private array $warnings = [];

    /**
     * @param  array<int, string>  $warnings
     */
    public function withWarnings(array $warnings): self
    {
        $this->warnings = $warnings;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();

        $subtotal = 0.0;
        $productDiscount = 0.0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            /** @var CartItem $item */
            $variant = $item->productVariant;
            $product = $variant?->product;

            $quantity = (int) $item->quantity;
            $totalQuantity += $quantity;

            $effectivePrice = (float) (
                $variant?->price_override
                ?? $product?->discount_price
                ?? $product?->regular_price
                ?? 0.00
            );

            $regularPrice = (float) ($product?->regular_price ?? $effectivePrice);

            $subtotal += round($effectivePrice * $quantity, 2);

            if ($regularPrice > $effectivePrice) {
                $productDiscount += round(($regularPrice - $effectivePrice) * $quantity, 2);
            }
        }

        $shippingEnabled = filter_var(
            AppSetting::query()
                ->where('setting_key', 'shipping_enabled')
                ->value('setting_value') ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );

        $shippingFee = (float) (
            AppSetting::query()
                ->where('setting_key', 'shipping_fee')
                ->value('setting_value') ?? 5.00
        );

        $shippingEstimate = $shippingEnabled && $items->isNotEmpty() ? $shippingFee : 0.00;

        $coupon = $this->relationLoaded('coupon') ? $this->coupon : ($this->coupon_id ? $this->coupon : null);
        $couponDiscount = 0.00;
        $couponData = null;

        if ($coupon) {
            $isValid = $coupon->is_active
                && ($coupon->valid_from === null || now()->gte($coupon->valid_from))
                && ($coupon->valid_until === null || now()->lte($coupon->valid_until))
                && ($coupon->usage_limit === null || $coupon->usage_count < $coupon->usage_limit)
                && ($subtotal >= (float) $coupon->min_order_amount);

            if ($isValid) {
                $couponDiscount = $coupon->calculateDiscount($subtotal);
                $couponData = [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => (float) $coupon->value,
                    'discount_amount' => round($couponDiscount, 2),
                ];
            }
        }

        $finalTotal = max(0.00, round($subtotal - $couponDiscount + $shippingEstimate, 2));

        $currency = app(CurrencyService::class)->getStoreCurrency();

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($items),
            'items_count' => $items->count(),
            'total_quantity' => $totalQuantity,
            'coupon' => $couponData,
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'product_discount' => round($productDiscount, 2),
                'coupon_discount' => round($couponDiscount, 2),
                'shipping_estimate' => round($shippingEstimate, 2),
                'final_total' => $finalTotal,
                'currency' => $currency,
            ],
            'warnings' => $this->warnings,
        ];
    }
}

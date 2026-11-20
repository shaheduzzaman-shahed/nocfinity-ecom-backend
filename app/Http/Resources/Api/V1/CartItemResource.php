<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\PresentsProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    use PresentsProduct;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->productVariant;
        $product = $variant?->product;

        $effectivePrice = (float) (
            $variant?->price_override
            ?? $product?->discount_price
            ?? $product?->regular_price
            ?? 0.00
        );

        $regularPrice = (float) ($product?->regular_price ?? 0.00);
        $hasDiscount = $effectivePrice < $regularPrice;
        $quantity = (int) $this->quantity;
        $lineSubtotal = round($effectivePrice * $quantity, 2);

        $primaryImage = null;
        if ($product && $product->relationLoaded('images')) {
            $imageModel = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
            $primaryImage = $this->publicImageUrl($imageModel?->image_url);
        }

        $stock = (int) ($variant?->stock ?? 0);
        $isVariantActive = (bool) ($variant?->is_active ?? false);
        $isProductActive = (bool) ($product?->is_active ?? false);
        $isAvailable = $isVariantActive && $isProductActive && $stock > 0;
        $isSufficientStock = $stock >= $quantity;

        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $quantity,
            'product_id' => $product?->id,
            'product_name' => $product?->name ?? 'Unavailable Product',
            'product_slug' => $product?->slug,
            'primary_image' => $primaryImage,
            'sku' => $variant?->sku ?? '',
            'color' => $variant && $variant->relationLoaded('color') && $variant->color ? [
                'id' => $variant->color->id,
                'name' => $variant->color->name,
                'hex_code' => $variant->color->hex_code,
            ] : null,
            'size' => $variant && $variant->relationLoaded('size') && $variant->size ? [
                'id' => $variant->size->id,
                'name' => $variant->size->name,
                'sort_order' => $variant->size->sort_order,
            ] : null,
            'unit_price' => $effectivePrice,
            'regular_price' => $regularPrice,
            'has_discount' => $hasDiscount,
            'line_subtotal' => $lineSubtotal,
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'is_available' => $isAvailable,
            'is_sufficient_stock' => $isSufficientStock,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;

class PublicProductResource extends ProductCardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(
                fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'color' => $this->colorPayload($variant),
                    'size' => $this->sizePayload($variant),
                    'sku' => $variant->sku,
                    'stock' => (int) $variant->stock,
                    'price_override' => $variant->price_override !== null
                        ? (float) $variant->price_override
                        : null,
                    'effective_price' => (float) (
                        $variant->price_override
                        ?? $this->discount_price
                        ?? $this->regular_price
                    ),
                ],
            )),
            'rating_breakdown' => $this->when(
                $this->rating_breakdown !== null,
                fn () => (object) $this->rating_breakdown,
            ),
            'related_products' => ProductCardResource::collection(
                $this->whenLoaded('relatedProducts'),
            ),
        ]);
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\PresentsProduct;
use App\Models\ProductVariant;
use App\Services\Settings\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductResource extends JsonResource
{
    use PresentsProduct;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : collect();

        return [
            'id' => $this->id,
            'category' => $this->categoryPayload(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'regular_price' => (float) $this->regular_price,
            'discount_price' => $this->discount_price !== null
                ? (float) $this->discount_price
                : null,
            'effective_price' => $this->effectivePrice(),
            'currency' => app(CurrencyService::class)->getStoreCurrency(),
            'is_active' => (bool) $this->is_active,
            'sku' => $variants->first()?->sku,
            'stock' => $this->totalStock(),
            'variants_count' => $this->relationLoaded('variants')
                ? $variants->count()
                : null,
            'primary_image' => $this->publicImageUrl($this->primaryImage()?->image_url),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => $this->whenLoaded('variants', fn () => $variants->map(
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
                    'is_active' => (bool) $variant->is_active,
                ],
            )),
            'average_rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

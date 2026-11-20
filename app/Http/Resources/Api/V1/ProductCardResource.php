<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\PresentsProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    use PresentsProduct;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->categoryPayload(),
            'primary_image' => $this->publicImageUrl($this->primaryImage()?->image_url),
            'regular_price' => (float) $this->regular_price,
            'discount_price' => $this->discount_price !== null
                ? (float) $this->discount_price
                : null,
            'effective_price' => $this->effectivePrice(),
            'discount_percentage' => $this->discountPercentage(),
            'stock' => $this->totalStock(),
            'in_stock' => $this->totalStock() > 0,
            'average_rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'currency' => $this->currency ?? 'USD',
        ];
    }
}

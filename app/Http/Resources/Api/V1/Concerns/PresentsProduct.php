<?php

namespace App\Http\Resources\Api\V1\Concerns;

use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;

trait PresentsProduct
{
    protected function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    protected function primaryImage(): ?ProductImage
    {
        if (! $this->relationLoaded('images')) {
            return null;
        }

        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    protected function effectivePrice(): float
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : collect();

        $prices = $variants->map(
            fn (ProductVariant $variant) => (float) (
                $variant->price_override
                ?? $this->discount_price
                ?? $this->regular_price
            ),
        );

        if ($prices->isNotEmpty()) {
            return (float) $prices->min();
        }

        return (float) ($this->discount_price ?? $this->regular_price);
    }

    protected function totalStock(): int
    {
        if (! $this->relationLoaded('variants')) {
            return 0;
        }

        return (int) $this->variants->sum(
            fn (ProductVariant $variant) => (int) $variant->stock,
        );
    }

    protected function discountPercentage(): ?int
    {
        $regularPrice = (float) $this->regular_price;
        $effectivePrice = $this->effectivePrice();

        if ($regularPrice <= 0 || $effectivePrice >= $regularPrice) {
            return null;
        }

        return (int) round((($regularPrice - $effectivePrice) / $regularPrice) * 100);
    }

    /**
     * @return array{id: int, name: string, hex_code: string|null}|null
     */
    protected function colorPayload(ProductVariant $variant): ?array
    {
        if (! $variant->relationLoaded('color') || ! $variant->color) {
            return null;
        }

        return [
            'id' => $variant->color->id,
            'name' => $variant->color->name,
            'hex_code' => $variant->color->hex_code,
        ];
    }

    /**
     * @return array{id: int, name: string, sort_order: int}|null
     */
    protected function sizePayload(ProductVariant $variant): ?array
    {
        if (! $variant->relationLoaded('size') || ! $variant->size) {
            return null;
        }

        return [
            'id' => $variant->size->id,
            'name' => $variant->size->name,
            'sort_order' => $variant->size->sort_order,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    protected function categoryPayload(): ?array
    {
        if (! $this->relationLoaded('category') || ! $this->category) {
            return null;
        }

        return [
            'id' => $this->category->id,
            'name' => $this->category->name,
            'slug' => $this->category->slug,
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Admin\Product\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

trait ValidatesProductPayload
{
    protected function productFromRoute(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function variantRules(bool $updating): array
    {
        return [
            'variants' => ['sometimes', 'array', 'max:200'],
            'variants.*.id' => $updating
                ? ['sometimes', 'integer', 'min:1']
                : ['prohibited'],
            'variants.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'variants.*.size_id' => ['required', 'integer', 'exists:sizes,id'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'variants.*.price_override' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function validateProductPayload(Validator $validator): void
    {
        $this->validateDiscountPrice($validator);
        $this->validateVariantPayload($validator);
    }

    private function validateDiscountPrice(Validator $validator): void
    {
        $product = $this->productFromRoute();
        $regularPrice = $this->input('regular_price', $product?->regular_price);
        $discountPrice = $this->input('discount_price', $product?->discount_price);

        if (
            $discountPrice === null
            || $discountPrice === ''
            || ! is_numeric($regularPrice)
            || ! is_numeric($discountPrice)
        ) {
            return;
        }

        if ((float) $discountPrice >= (float) $regularPrice) {
            $validator->errors()->add(
                'discount_price',
                'The discount price must be less than the regular price.',
            );
        }
    }

    private function validateVariantPayload(Validator $validator): void
    {
        $variants = $this->input('variants');

        if (! is_array($variants)) {
            return;
        }

        $product = $this->productFromRoute();
        $existingIds = $product
            ? $product->variants()->pluck('id')->all()
            : [];
        $payloadIds = collect($variants)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $ignoredIds = $product
            ? array_values(array_diff($existingIds, $payloadIds))
            : [];
        $seenCombinations = [];
        $seenSkus = [];
        $seenIds = [];

        foreach ($variants as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $variantId = isset($variant['id']) ? (int) $variant['id'] : null;

            if ($variantId !== null) {
                if (isset($seenIds[$variantId])) {
                    $validator->errors()->add(
                        "variants.$index.id",
                        'This variant is included more than once.',
                    );
                }

                $seenIds[$variantId] = true;

                if ($product && ! in_array($variantId, $existingIds, true)) {
                    $validator->errors()->add(
                        "variants.$index.id",
                        'This variant does not belong to the product.',
                    );
                }
            }

            $sku = $variant['sku'] ?? null;

            if (is_string($sku) && trim($sku) !== '') {
                $sku = trim($sku);
                $skuKey = Str::lower($sku);

                if (isset($seenSkus[$skuKey])) {
                    $validator->errors()->add(
                        "variants.$index.sku",
                        'This SKU is used by another variant.',
                    );
                }

                $seenSkus[$skuKey] = true;

                $query = ProductVariant::query()->whereRaw('LOWER(sku) = ?', [$skuKey]);

                if ($variantId !== null) {
                    $query->where('id', '!=', $variantId);
                }

                if ($ignoredIds !== []) {
                    $query->whereNotIn('id', $ignoredIds);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        "variants.$index.sku",
                        'This SKU is already in use.',
                    );
                }
            }

            $colorId = isset($variant['color_id']) ? (int) $variant['color_id'] : null;
            $sizeId = isset($variant['size_id']) ? (int) $variant['size_id'] : null;

            if ($colorId === null || $sizeId === null) {
                continue;
            }

            $combinationKey = "{$colorId}-{$sizeId}";

            if (isset($seenCombinations[$combinationKey])) {
                $validator->errors()->add(
                    "variants.$index.size_id",
                    'This color and size combination is already used by another variant.',
                );
            }

            $seenCombinations[$combinationKey] = true;

            if ($product) {
                $query = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('color_id', $colorId)
                    ->where('size_id', $sizeId);

                if ($variantId !== null) {
                    $query->where('id', '!=', $variantId);
                }

                if ($ignoredIds !== []) {
                    $query->whereNotIn('id', $ignoredIds);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        "variants.$index.size_id",
                        'This color and size combination already exists for the product.',
                    );
                }
            }
        }
    }
}

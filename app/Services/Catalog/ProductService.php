<?php

namespace App\Services\Catalog;

use App\Events\Catalog\ProductCreated;
use App\Events\Catalog\ProductDeleted;
use App\Events\Catalog\ProductUpdated;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductService
{
    public function __construct(
        private readonly ProductImageService $imageService,
    ) {}

    public function adminPaginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->with($this->adminRelations());

        $this->withReviewAggregates($query);

        $search = $filters['search'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(
                fn (Builder $nested) => $nested
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term),
            );
        }

        $categoryId = $filters['category_id'] ?? null;

        if ($categoryId !== null && $categoryId !== '') {
            $query->whereIn(
                'category_id',
                $this->categorySubtreeIds((int) $categoryId),
            );
        }

        $isActive = $filters['is_active'] ?? null;

        if ($isActive !== null && $isActive !== '') {
            $query->where(
                'is_active',
                filter_var($isActive, FILTER_VALIDATE_BOOLEAN),
            );
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(Product $product): Product
    {
        $query = Product::query()
            ->whereKey($product->id)
            ->with($this->adminRelations());

        $this->withReviewAggregates($query);

        return $query->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create([
                'category_id' => (int) $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->generateSlug($data['name']),
                'description' => $data['description'] ?? null,
                'regular_price' => $data['regular_price'],
                'discount_price' => $data['discount_price'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            }

            $savedProduct = $this->find($product);

            ProductCreated::dispatch($savedProduct);

            return $savedProduct;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $attributes = Arr::only($data, [
                'category_id',
                'name',
                'description',
                'regular_price',
                'discount_price',
                'is_active',
            ]);

            if (
                array_key_exists('name', $attributes)
                && $attributes['name'] !== $product->name
            ) {
                $attributes['slug'] = $this->generateSlug(
                    $attributes['name'],
                    $product,
                );
            }

            if ($attributes !== []) {
                $product->update($attributes);
            }

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            }

            $updatedProduct = $this->find($product->fresh());

            ProductUpdated::dispatch($updatedProduct);

            return $updatedProduct;
        });
    }

    public function delete(Product $product): void
    {
        $imagePaths = $product->images()->pluck('image_url')->all();

        ProductDeleted::dispatch($product);

        DB::transaction(fn () => $product->delete());

        $this->imageService->deleteFiles($imagePaths);
    }

    public function publicPaginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with($this->publicRelations());

        $this->withReviewAggregates($query);
        $this->applyPublicFilters($query, $filters);
        $this->applyPublicSort($query, $filters['sort'] ?? null);

        $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 48);

        $products = $query->paginate($perPage)->withQueryString();

        $this->attachCurrency($products->getCollection());

        return $products;
    }

    public function findPublic(Product $product): Product
    {
        if (! $product->is_active) {
            throw new NotFoundHttpException('Product not found.');
        }

        $query = Product::query()
            ->whereKey($product->id)
            ->where('is_active', true)
            ->with($this->publicRelations());

        $this->withReviewAggregates($query);

        $product = $query->firstOrFail();

        $product->setAttribute(
            'rating_breakdown',
            $this->ratingBreakdown($product->id),
        );

        $relatedQuery = Product::query()
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with($this->publicRelations());

        $this->withReviewAggregates($relatedQuery);

        $related = $relatedQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        $this->attachCurrency($related);
        $product->setRelation('relatedProducts', $related);
        $this->attachCurrency(collect([$product]));

        return $product;
    }

    /**
     * @return Collection<int, Product>
     */
    public function featured(int $limit = 8): Collection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->whereHas(
                'variants',
                fn ($variant) => $variant
                    ->where('is_active', true)
                    ->where('stock', '>', 0),
            )
            ->with($this->publicRelations());

        $this->withReviewAggregates($query);

        $products = $query
            ->orderByRaw(
                'CASE WHEN products.discount_price IS NOT NULL THEN 0 ELSE 1 END',
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(min(max($limit, 1), 24))
            ->get();

        $this->attachCurrency($products);

        return $products;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $existingIds = $product->variants()->pluck('id')->all();
        $payloadIds = collect($variants)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $removedIds = array_values(array_diff($existingIds, $payloadIds));

        if ($removedIds !== []) {
            $product->variants()->whereIn('id', $removedIds)->delete();
        }

        if ($payloadIds === [] && $variants === []) {
            return;
        }

        $existing = $product->variants()->get()->keyBy('id');

        foreach ($variants as $row) {
            $attributes = [
                'color_id' => (int) $row['color_id'],
                'size_id' => (int) $row['size_id'],
                'sku' => trim((string) $row['sku']),
                'stock' => (int) $row['stock'],
                'price_override' => $row['price_override'] ?? null,
                'is_active' => $row['is_active'] ?? true,
            ];

            if (isset($row['id'])) {
                $variant = $existing->get((int) $row['id']);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'variants' => 'The selected variant does not belong to this product.',
                    ]);
                }

                $variant->update($attributes);

                continue;
            }

            $product->variants()->create($attributes);
        }
    }

    private function generateSlug(string $name, ?Product $ignore = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'product', 240, '');
        $slug = $base;
        $counter = 2;

        while (
            Product::query()
                ->where('slug', $slug)
                ->when(
                    $ignore,
                    fn (Builder $query) => $query->where('id', '!=', $ignore->id),
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<int, string>
     */
    private function adminRelations(): array
    {
        return [
            'category:id,name,slug',
            'images' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id'),
            'variants' => fn ($query) => $query
                ->with([
                    'color:id,name,hex_code',
                    'size:id,name,sort_order',
                ])
                ->orderBy('id'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function publicRelations(): array
    {
        return [
            'category:id,name,slug',
            'images' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id'),
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->with([
                    'color:id,name,hex_code',
                    'size:id,name,sort_order',
                ])
                ->orderBy('id'),
        ];
    }

    private function withReviewAggregates(Builder $query): void
    {
        $query
            ->withCount([
                'reviews as reviews_count' => fn ($review) => $review
                    ->where('status', 'approved'),
            ])
            ->withAvg(
                [
                    'reviews as reviews_avg_rating' => fn ($review) => $review
                        ->where('status', 'approved'),
                ],
                'rating',
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyPublicFilters(Builder $query, array $filters): void
    {
        $search = $filters['search'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(
                fn (Builder $nested) => $nested
                    ->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term),
            );
        }

        $categoryId = $filters['category_id'] ?? null;

        if ($categoryId !== null && $categoryId !== '') {
            $query->whereIn(
                'category_id',
                $this->categorySubtreeIds((int) $categoryId, true),
            );
        }

        $minPrice = $filters['min_price'] ?? null;

        if ($minPrice !== null && $minPrice !== '' && is_numeric($minPrice)) {
            $query->whereRaw(
                $this->effectivePriceSql().' >= '.sprintf('%.2F', (float) $minPrice),
            );
        }

        $maxPrice = $filters['max_price'] ?? null;

        if ($maxPrice !== null && $maxPrice !== '' && is_numeric($maxPrice)) {
            $query->whereRaw(
                $this->effectivePriceSql().' <= '.sprintf('%.2F', (float) $maxPrice),
            );
        }

        $availability = $filters['availability'] ?? null;

        if ($availability === 'in_stock') {
            $query->whereHas(
                'variants',
                fn ($variant) => $variant
                    ->where('is_active', true)
                    ->where('stock', '>', 0),
            );
        }

        if ($availability === 'out_of_stock') {
            $query->whereDoesntHave(
                'variants',
                fn ($variant) => $variant
                    ->where('is_active', true)
                    ->where('stock', '>', 0),
            );
        }

        $colorIds = $this->parseIdList($filters['color_id'] ?? $filters['color_ids'] ?? null);

        if ($colorIds !== []) {
            $query->whereHas(
                'variants',
                fn ($variant) => $variant
                    ->where('is_active', true)
                    ->whereIn('color_id', $colorIds),
            );
        }

        $sizeIds = $this->parseIdList($filters['size_id'] ?? $filters['size_ids'] ?? null);

        if ($sizeIds !== []) {
            $query->whereHas(
                'variants',
                fn ($variant) => $variant
                    ->where('is_active', true)
                    ->whereIn('size_id', $sizeIds),
            );
        }
    }

    private function applyPublicSort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query
                ->orderByRaw($this->effectivePriceSql().' ASC')
                ->orderBy('id'),
            'price_desc' => $query
                ->orderByRaw($this->effectivePriceSql().' DESC')
                ->orderBy('id'),
            default => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        };
    }

    private function effectivePriceSql(): string
    {
        return 'COALESCE((SELECT MIN(COALESCE(pv.price_override, products.discount_price, products.regular_price)) FROM product_variants pv WHERE pv.product_id = products.id AND pv.is_active = 1), COALESCE(products.discount_price, products.regular_price))';
    }

    /**
     * @return array<int, int>
     */
    private function categorySubtreeIds(int $categoryId, bool $activeOnly = false): array
    {
        $rootQuery = Category::query()->whereKey($categoryId);

        if ($activeOnly) {
            $rootQuery->where('is_active', true);
        }

        if (! $rootQuery->exists()) {
            return [];
        }

        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $childrenQuery = Category::query()->whereIn('parent_id', $frontier);

            if ($activeOnly) {
                $childrenQuery->where('is_active', true);
            }

            $newIds = array_values(array_diff(
                $childrenQuery->pluck('id')->all(),
                $ids,
            ));

            if ($newIds === []) {
                break;
            }

            $ids = array_merge($ids, $newIds);
            $frontier = $newIds;
        }

        return $ids;
    }

    /**
     * @return array<string, int>
     */
    private function ratingBreakdown(int $productId): array
    {
        $counts = ProductReview::query()
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $breakdown = [];

        foreach ([5, 4, 3, 2, 1] as $star) {
            $breakdown[(string) $star] = (int) ($counts->get($star) ?? 0);
        }

        return $breakdown;
    }

    /**
     * @param  iterable<int, Product>  $products
     */
    private function attachCurrency(iterable $products): void
    {
        $currency = (string) (
            AppSetting::query()
                ->where('setting_key', 'store_currency')
                ->value('setting_value') ?? 'USD'
        );

        foreach ($products as $product) {
            $product->setAttribute('currency', $currency);
        }
    }

    /**
     * @return array<int, int>
     */
    private function parseIdList(mixed $value): array
    {
        if (is_numeric($value)) {
            $id = (int) $value;

            return $id > 0 ? [$id] : [];
        }

        if (is_string($value) && trim($value) !== '') {
            $parts = explode(',', $value);

            return array_values(array_filter(
                array_map(fn ($p) => is_numeric(trim($p)) ? (int) trim($p) : null, $parts),
                fn ($id) => $id !== null && $id > 0,
            ));
        }

        if (is_array($value)) {
            return array_values(array_filter(
                array_map(fn ($item) => is_numeric($item) ? (int) $item : null, $value),
                fn ($id) => $id !== null && $id > 0,
            ));
        }

        return [];
    }
}

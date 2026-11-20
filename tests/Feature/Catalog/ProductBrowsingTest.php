<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $productAttributes
 * @param  array<string, mixed>  $variantAttributes
 * @return array{0: Product, 1: ProductVariant}
 */
function makeCatalogProduct(array $productAttributes = [], array $variantAttributes = []): array
{
    $product = Product::factory()->create($productAttributes);
    $variant = ProductVariant::factory()->for($product)->create($variantAttributes);

    return [$product, $variant];
}

test('the public product listing only returns active products', function () {
    [$active] = makeCatalogProduct(['name' => 'Active Product']);
    makeCatalogProduct(['name' => 'Inactive Product', 'is_active' => false]);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Products retrieved successfully.')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Active Product')
        ->assertJsonPath('data.0.id', $active->id);
});

test('public product listing includes active descendant categories', function () {
    $parent = Category::factory()->create(['is_active' => true]);
    $child = Category::factory()->create([
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
    $hiddenChild = Category::factory()->create([
        'parent_id' => $parent->id,
        'is_active' => false,
    ]);

    makeCatalogProduct([
        'name' => 'Child Product',
        'category_id' => $child->id,
    ]);
    makeCatalogProduct([
        'name' => 'Hidden Category Product',
        'category_id' => $hiddenChild->id,
    ]);

    $this->getJson('/api/v1/products?category_id='.$parent->id)
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Child Product');

    $this->getJson('/api/v1/products?category_id='.$hiddenChild->id)
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

test('public product listing supports search by name and description', function () {
    makeCatalogProduct([
        'name' => 'Linen Summer Shirt',
        'description' => 'Lightweight warm weather layer.',
    ]);
    makeCatalogProduct([
        'name' => 'Wool Overcoat',
        'description' => 'A linen-lined coat for cool evenings.',
    ]);
    makeCatalogProduct([
        'name' => 'Leather Tote',
        'description' => 'Everyday carry.',
    ]);

    $this->getJson('/api/v1/products?search=Linen')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/products?search=Wool+Overcoat')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Wool Overcoat');
});

test('public product price filters use the effective selling price', function () {
    makeCatalogProduct(
        ['name' => 'Discounted Dress', 'regular_price' => 100, 'discount_price' => 80],
    );
    makeCatalogProduct(
        ['name' => 'Override Tee', 'regular_price' => 60],
        ['price_override' => 45],
    );
    makeCatalogProduct(
        ['name' => 'Full Price Pants', 'regular_price' => 90],
    );

    $response = $this->getJson('/api/v1/products?min_price=50&max_price=85');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Discounted Dress')
        ->assertJsonPath('data.0.effective_price', 80);
});

test('public product listing filters by availability', function () {
    makeCatalogProduct(['name' => 'In Stock Tee'], ['stock' => 5]);
    makeCatalogProduct(['name' => 'Sold Out Tee'], ['stock' => 0]);
    makeCatalogProduct(['name' => 'Inactive Variant Tee'], [
        'stock' => 50,
        'is_active' => false,
    ]);

    $this->getJson('/api/v1/products?availability=in_stock')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'In Stock Tee');

    $response = $this->getJson('/api/v1/products?availability=out_of_stock');

    $response->assertOk()->assertJsonPath('meta.total', 2);
    expect(collect($response->json('data'))->pluck('name')->all())
        ->toContain('Sold Out Tee', 'Inactive Variant Tee');
});

test('public product listing sorts by effective price and recency', function () {
    makeCatalogProduct(
        ['name' => 'Cheap Tee', 'created_at' => now()->subDay()],
        ['price_override' => 45],
    );
    makeCatalogProduct(
        ['name' => 'Pricey Tee', 'created_at' => now()],
        ['price_override' => 80],
    );

    $this->getJson('/api/v1/products?sort=price_asc')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Cheap Tee');

    $this->getJson('/api/v1/products?sort=price_desc')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Pricey Tee');

    $this->getJson('/api/v1/products?sort=newest')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Pricey Tee');

});

test('public product listing paginates on the server', function () {
    Product::factory()->count(15)->create();

    $firstPage = $this->getJson('/api/v1/products?per_page=10&page=1');

    $firstPage->assertOk()
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.from', 1)
        ->assertJsonPath('meta.to', 10);
    expect($firstPage->json('data'))->toHaveCount(10);

    $secondPage = $this->getJson('/api/v1/products?per_page=10&page=2');
    $secondPage->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.from', 11)
        ->assertJsonPath('meta.to', 15);
    expect($secondPage->json('data'))->toHaveCount(5);
});

test('the public product detail returns variants, images, ratings and related products', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'name' => 'Detailed Dress',
        'description' => 'A detailed dress.',
        'regular_price' => 100,
        'discount_price' => 80,
    ]);

    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/detail-primary.jpg',
    ]);
    ProductImage::factory()->for($product)->create([
        'image_url' => 'products/detail-secondary.jpg',
        'sort_order' => 1,
    ]);

    $color = Color::factory()->create(['name' => 'Red', 'hex_code' => '#FF0000']);
    $size = Size::factory()->create(['name' => 'M', 'sort_order' => 2]);

    ProductVariant::factory()->for($product)->create([
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-DETAIL',
        'stock' => 6,
    ]);

    ProductReview::factory()->for($product)->approved()->rating(5)->create();
    ProductReview::factory()->for($product)->approved()->rating(3)->create();
    ProductReview::factory()->for($product)->rating(1)->create();

    $related = Product::factory()->for($category)->create([
        'name' => 'Related Dress',
    ]);

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Detailed Dress')
        ->assertJsonPath('data.effective_price', 80)
        ->assertJsonPath('data.stock', 6)
        ->assertJsonPath('data.in_stock', true)
        ->assertJsonPath('data.average_rating', 4)
        ->assertJsonPath('data.reviews_count', 2)
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.variants.0.sku', 'SKU-DETAIL')
        ->assertJsonPath('data.variants.0.color.name', 'Red')
        ->assertJsonPath('data.variants.0.size.name', 'M')
        ->assertJsonPath('data.related_products.0.id', $related->id);

    expect($response->json('data.images'))->toHaveCount(2);
    expect($response->json('data.rating_breakdown'))->toBe([
        '5' => 1,
        '4' => 0,
        '3' => 1,
        '2' => 0,
        '1' => 0,
    ]);
});

test('inactive and missing products are not visible publicly', function () {
    $inactive = Product::factory()->inactive()->create();

    $this->getJson("/api/v1/products/{$inactive->id}")->assertStatus(404);
    $this->getJson('/api/v1/products/999')->assertStatus(404);
});

test('featured products are active, in stock, and discount first', function () {
    Product::factory()->create([
        'name' => 'Featured Discount',
        'discount_price' => 80,
        'created_at' => now(),
    ])->variants()->create([
        'color_id' => Color::factory()->create()->id,
        'size_id' => Size::factory()->create()->id,
        'sku' => 'FEATURED-1',
        'stock' => 5,
        'is_active' => true,
    ]);

    Product::factory()->create([
        'name' => 'Featured Full Price',
        'created_at' => now()->subMinute(),
    ])->variants()->create([
        'color_id' => Color::factory()->create()->id,
        'size_id' => Size::factory()->create()->id,
        'sku' => 'FEATURED-2',
        'stock' => 5,
        'is_active' => true,
    ]);

    Product::factory()->inactive()->create([
        'name' => 'Hidden Featured',
        'discount_price' => 10,
    ])->variants()->create([
        'color_id' => Color::factory()->create()->id,
        'size_id' => Size::factory()->create()->id,
        'sku' => 'FEATURED-3',
        'stock' => 5,
        'is_active' => true,
    ]);

    $soldOut = Product::factory()->create([
        'name' => 'Sold Out Featured',
        'discount_price' => 10,
    ]);

    ProductVariant::factory()->for($soldOut)->outOfStock()->create();

    $response = $this->getJson('/api/v1/featured-products');

    $response->assertOk()
        ->assertJsonPath('message', 'Featured products retrieved successfully.');

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toBe(['Featured Discount', 'Featured Full Price']);
});

test('product cards expose pricing, stock, image and review data', function () {
    $product = Product::factory()->create([
        'name' => 'Card Product',
        'regular_price' => 100,
        'discount_price' => 80,
    ]);

    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/card.jpg',
    ]);

    ProductVariant::factory()->for($product)->create(['stock' => 3]);
    ProductReview::factory()->for($product)->approved()->rating(5)->create();
    ProductReview::factory()->for($product)->approved()->rating(4)->create();

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
        ->assertJsonPath('data.0.effective_price', 80)
        ->assertJsonPath('data.0.discount_percentage', 20)
        ->assertJsonPath('data.0.stock', 3)
        ->assertJsonPath('data.0.in_stock', true)
        ->assertJsonPath('data.0.average_rating', 4.5)
        ->assertJsonPath('data.0.reviews_count', 2)
        ->assertJsonPath('data.0.currency', 'USD');

    expect($response->json('data.0.primary_image'))
        ->toContain('/storage/products/card.jpg');
});

<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions
 */
function actingAsProductStaff(string ...$permissions): User
{
    $user = staffWithPermissions($permissions);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

test('staff with products.view can search and paginate products', function () {
    actingAsProductStaff('products.view');

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'name' => 'Alpha Shirt',
        'slug' => 'alpha-shirt',
    ]);
    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/alpha.jpg',
    ]);
    ProductVariant::factory()->for($product)->create([
        'sku' => 'SKU-ALPHA',
        'stock' => 12,
    ]);
    Product::factory()->create(['name' => 'Beta Hat']);

    $response = $this->getJson('/api/v1/admin/products?search=Alpha&per_page=10');

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Products retrieved successfully.',
    ]);

    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.name', 'Alpha Shirt');
    $response->assertJsonPath('data.0.sku', 'SKU-ALPHA');
    $response->assertJsonPath('data.0.stock', 12);
    $response->assertJsonPath('data.0.variants_count', 1);
    $response->assertJsonPath('data.0.category.id', $category->id);
    expect($response->json('data.0.images'))->toHaveCount(1);
});

test('product listing can filter by category and active status', function () {
    actingAsProductStaff('products.view');

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    Product::factory()->for($child)->create(['name' => 'Child Product']);
    Product::factory()->for($parent)->create(['name' => 'Parent Product']);
    Product::factory()->for($parent)->inactive()->create(['name' => 'Hidden Product']);

    $this->getJson('/api/v1/admin/products?category_id='.$parent->id.'&per_page=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/v1/admin/products?category_id='.$parent->id.'&is_active=0')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Hidden Product');
});

test('listing products requires products.view', function () {
    Sanctum::actingAs(staffWithPermissions(['categories.view']), ['admin']);

    $this->getJson('/api/v1/admin/products')->assertStatus(403);
});

test('customers cannot access product management', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/products')->assertStatus(403);
    $this->postJson('/api/v1/admin/products', [])->assertStatus(403);
});

test('staff with products.create can create a product with variants', function () {
    actingAsProductStaff('products.create');

    $category = Category::factory()->create();
    $color = Color::factory()->create(['name' => 'Navy']);
    $size = Size::factory()->create(['name' => 'M', 'sort_order' => 2]);

    $response = $this->postJson('/api/v1/admin/products', [
        'name' => 'Test Jacket',
        'description' => 'A lightweight jacket.',
        'category_id' => $category->id,
        'regular_price' => 120,
        'discount_price' => 99.5,
        'is_active' => true,
        'variants' => [
            [
                'color_id' => $color->id,
                'size_id' => $size->id,
                'sku' => 'SKU-JACKET-1',
                'stock' => 5,
                'price_override' => null,
                'is_active' => true,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Jacket')
        ->assertJsonPath('data.slug', 'test-jacket')
        ->assertJsonPath('data.variants_count', 1)
        ->assertJsonPath('data.sku', 'SKU-JACKET-1')
        ->assertJsonPath('data.stock', 5)
        ->assertJsonPath('data.effective_price', 99.5)
        ->assertJsonPath('data.variants.0.color.name', 'Navy')
        ->assertJsonPath('data.variants.0.size.name', 'M');

    $this->assertDatabaseHas('products', [
        'name' => 'Test Jacket',
        'slug' => 'test-jacket',
        'regular_price' => 120,
        'discount_price' => 99.5,
    ]);
    $this->assertDatabaseHas('product_variants', [
        'sku' => 'SKU-JACKET-1',
        'stock' => 5,
    ]);
});

test('product slugs are unique', function () {
    actingAsProductStaff('products.create');

    Product::factory()->create([
        'name' => 'Classic Tee',
        'slug' => 'classic-tee',
    ]);

    $this->postJson('/api/v1/admin/products', [
        'name' => 'Classic Tee',
        'category_id' => Category::factory()->create()->id,
        'regular_price' => 20,
    ])->assertCreated()->assertJsonPath('data.slug', 'classic-tee-2');
});

test('product creation validates its core fields', function () {
    actingAsProductStaff('products.create');

    $this->postJson('/api/v1/admin/products', [
        'name' => '',
        'category_id' => 999,
        'regular_price' => -1,
        'discount_price' => 5,
    ])->assertStatus(422)->assertJsonValidationErrors([
        'name',
        'category_id',
        'regular_price',
    ]);

    $category = Category::factory()->create();

    $this->postJson('/api/v1/admin/products', [
        'name' => 'Bad Discount',
        'category_id' => $category->id,
        'regular_price' => 50,
        'discount_price' => 50,
    ])->assertStatus(422)->assertJsonValidationErrors(['discount_price']);
});

test('product variants must use unique skus and combinations', function () {
    actingAsProductStaff('products.create');

    $category = Category::factory()->create();
    $color = Color::factory()->create();
    $size = Size::factory()->create();

    $variant = [
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SKU-DUPLICATE',
        'stock' => 1,
    ];

    $this->postJson('/api/v1/admin/products', [
        'name' => 'Duplicate Combination',
        'category_id' => $category->id,
        'regular_price' => 50,
        'variants' => [$variant, $variant],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'variants.0.sku',
        'variants.1.sku',
        'variants.1.size_id',
    ]);

    ProductVariant::factory()->create(['sku' => 'SKU-EXISTING']);

    $this->postJson('/api/v1/admin/products', [
        'name' => 'Duplicate SKU Product',
        'category_id' => $category->id,
        'regular_price' => 50,
        'variants' => [[
            'color_id' => $color->id,
            'size_id' => $size->id,
            'sku' => 'sku-existing',
            'stock' => 1,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors(['variants.0.sku']);
});

test('staff with products.edit can update a product and sync variants', function () {
    actingAsProductStaff('products.edit');

    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);

    $colorA = Color::factory()->create();
    $sizeA = Size::factory()->create();
    $colorB = Color::factory()->create();
    $sizeB = Size::factory()->create();

    $kept = ProductVariant::factory()->for($product)->create([
        'color_id' => $colorA->id,
        'size_id' => $sizeA->id,
        'sku' => 'SKU-KEPT',
        'stock' => 2,
    ]);

    $removed = ProductVariant::factory()->for($product)->create([
        'color_id' => $colorB->id,
        'size_id' => $sizeB->id,
        'sku' => 'SKU-REMOVED',
    ]);

    $response = $this->putJson("/api/v1/admin/products/{$product->id}", [
        'name' => 'New Name',
        'regular_price' => 80,
        'variants' => [
            [
                'id' => $kept->id,
                'color_id' => $colorA->id,
                'size_id' => $sizeA->id,
                'sku' => 'SKU-KEPT',
                'stock' => 9,
            ],
            [
                'color_id' => $colorB->id,
                'size_id' => $sizeB->id,
                'sku' => 'SKU-NEW',
                'stock' => 4,
                'price_override' => 65,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.slug', 'new-name')
        ->assertJsonPath('data.variants_count', 2)
        ->assertJsonPath('data.stock', 13);

    expect($response->json('data.variants'))
        ->toHaveCount(2)
        ->and(collect($response->json('data.variants'))->firstWhere('sku', 'SKU-KEPT')['stock'])
        ->toBe(9);

    $this->assertDatabaseHas('product_variants', [
        'id' => $kept->id,
        'stock' => 9,
    ]);
    $this->assertDatabaseHas('product_variants', ['sku' => 'SKU-NEW']);
    $this->assertDatabaseMissing('product_variants', ['id' => $removed->id]);
});

test('updating a product keeps its slug when the name is unchanged', function () {
    actingAsProductStaff('products.edit');

    $product = Product::factory()->create([
        'name' => 'Stable Name',
        'slug' => 'stable-name',
    ]);

    $this->putJson("/api/v1/admin/products/{$product->id}", [
        'regular_price' => 42,
    ])->assertOk()->assertJsonPath('data.slug', 'stable-name');
});

test('product updates reject variants from another product', function () {
    actingAsProductStaff('products.edit');

    $product = Product::factory()->create();
    $otherVariant = ProductVariant::factory()->create();

    $this->putJson("/api/v1/admin/products/{$product->id}", [
        'variants' => [[
            'id' => $otherVariant->id,
            'color_id' => $otherVariant->color_id,
            'size_id' => $otherVariant->size_id,
            'sku' => 'SKU-OTHER',
            'stock' => 1,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors(['variants.0.id']);
});

test('product updates validate the combined discount price', function () {
    actingAsProductStaff('products.edit');

    $product = Product::factory()->create([
        'regular_price' => 100,
        'discount_price' => 80,
    ]);

    $this->putJson("/api/v1/admin/products/{$product->id}", [
        'regular_price' => 70,
    ])->assertStatus(422)->assertJsonValidationErrors(['discount_price']);
});

test('staff with products.view can view a product', function () {
    actingAsProductStaff('products.view');

    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $this->getJson("/api/v1/admin/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.variants.0.id', $variant->id);
});

test('missing products return 404', function () {
    actingAsProductStaff('products.view');

    $this->getJson('/api/v1/admin/products/999')->assertStatus(404);
});

test('staff with products.delete can delete a product and its files', function () {
    actingAsProductStaff('products.delete');

    Storage::fake('public');

    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $path = 'products/delete-me.jpg';
    Storage::disk('public')->put($path, 'image-data');

    $image = ProductImage::factory()->for($product)->primary()->create([
        'image_url' => $path,
    ]);

    $this->deleteJson("/api/v1/admin/products/{$product->id}")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    Storage::disk('public')->assertMissing($path);
});

test('product mutations require the matching product permissions', function () {
    actingAsProductStaff('products.view');

    $product = Product::factory()->create();

    $this->postJson('/api/v1/admin/products', [
        'name' => 'Forbidden',
        'category_id' => Category::factory()->create()->id,
        'regular_price' => 10,
    ])->assertStatus(403);

    $this->putJson("/api/v1/admin/products/{$product->id}", [
        'name' => 'Forbidden',
    ])->assertStatus(403);

    $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertStatus(403);
});

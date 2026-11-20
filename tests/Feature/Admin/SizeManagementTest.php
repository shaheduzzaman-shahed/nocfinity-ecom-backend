<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function actingAsSizeStaff(string $permission): User
{
    $user = staffWithPermissions([$permission]);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

function variantUsingSize(Size $size): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Test Product',
        'slug' => 'test-product-'.Str::lower(Str::random(8)),
        'regular_price' => 19.99,
        'is_active' => true,
    ]);

    return ProductVariant::create([
        'product_id' => $product->id,
        'color_id' => Color::factory()->create()->id,
        'size_id' => $size->id,
        'sku' => 'SKU-'.Str::upper(Str::random(10)),
        'stock' => 5,
        'is_active' => true,
    ]);
}

test('staff with products.view can list sizes in display order', function () {
    actingAsSizeStaff('products.view');

    $large = Size::factory()->create(['name' => 'L', 'sort_order' => 2]);
    Size::factory()->create(['name' => 'S', 'sort_order' => 0]);
    Size::factory()->create(['name' => 'M', 'sort_order' => 1]);
    Size::factory()->create(['name' => 'A', 'sort_order' => 2]);
    variantUsingSize($large);

    $response = $this->getJson('/api/v1/admin/sizes');

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Sizes retrieved successfully.',
    ]);

    $data = $response->json('data');

    expect(collect($data)->pluck('name')->all())->toBe(['S', 'M', 'A', 'L'])
        ->and(collect($data)->firstWhere('name', 'L')['product_variants_count'])->toBe(1)
        ->and(collect($data)->firstWhere('name', 'S')['product_variants_count'])->toBe(0);
});

test('listing sizes requires the products.view permission', function () {
    Sanctum::actingAs(staffWithPermissions(['categories.view']), ['admin']);

    $this->getJson('/api/v1/admin/sizes')->assertStatus(403);
});

test('customers cannot manage sizes', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/sizes')->assertStatus(403);
});

test('staff with products.create can create a size', function () {
    actingAsSizeStaff('products.create');

    $response = $this->postJson('/api/v1/admin/sizes', [
        'name' => 'XXL',
        'sort_order' => 6,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'XXL')
        ->assertJsonPath('data.sort_order', 6)
        ->assertJsonPath('data.product_variants_count', 0);

    $this->assertDatabaseHas('sizes', ['name' => 'XXL', 'sort_order' => 6]);
});

test('a size defaults to sort order zero', function () {
    actingAsSizeStaff('products.create');

    $this->postJson('/api/v1/admin/sizes', ['name' => 'One Size'])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 0);
});

test('size names must be unique regardless of case', function () {
    actingAsSizeStaff('products.create');

    Size::factory()->create(['name' => 'XL']);

    $this->postJson('/api/v1/admin/sizes', ['name' => 'xl'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('size creation validates its input', function () {
    actingAsSizeStaff('products.create');

    $this->postJson('/api/v1/admin/sizes', [
        'name' => '',
        'sort_order' => -1,
    ])->assertStatus(422)->assertJsonValidationErrors(['name', 'sort_order']);

    $this->postJson('/api/v1/admin/sizes', [
        'name' => 'M',
        'sort_order' => 'first',
    ])->assertStatus(422)->assertJsonValidationErrors(['sort_order']);
});

test('staff with products.edit can update a size', function () {
    actingAsSizeStaff('products.edit');

    $size = Size::factory()->create(['name' => 'Large', 'sort_order' => 3]);

    $this->putJson("/api/v1/admin/sizes/{$size->id}", [
        'name' => 'L',
        'sort_order' => 4,
    ])->assertOk()
        ->assertJsonPath('data.name', 'L')
        ->assertJsonPath('data.sort_order', 4);

    $this->assertDatabaseHas('sizes', [
        'id' => $size->id,
        'name' => 'L',
        'sort_order' => 4,
    ]);
});

test('updating a size can keep its existing name', function () {
    actingAsSizeStaff('products.edit');

    $size = Size::factory()->create(['name' => 'XL', 'sort_order' => 4]);

    $this->putJson("/api/v1/admin/sizes/{$size->id}", [
        'name' => 'xl',
        'sort_order' => 5,
    ])->assertOk()->assertJsonPath('data.sort_order', 5);
});

test('updating a size rejects a name used by another size', function () {
    actingAsSizeStaff('products.edit');

    $size = Size::factory()->create(['name' => 'XL']);
    Size::factory()->create(['name' => 'XXL']);

    $this->putJson("/api/v1/admin/sizes/{$size->id}", ['name' => 'xxl'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('staff with products.delete can delete an unused size', function () {
    actingAsSizeStaff('products.delete');

    $size = Size::factory()->create(['name' => 'Temporary']);

    $this->deleteJson("/api/v1/admin/sizes/{$size->id}")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Size deleted successfully.',
        ]);

    $this->assertDatabaseMissing('sizes', ['id' => $size->id]);
});

test('a size used by a product variant cannot be deleted', function () {
    actingAsSizeStaff('products.delete');

    $size = Size::factory()->create(['name' => 'M']);
    variantUsingSize($size);

    $this->deleteJson("/api/v1/admin/sizes/{$size->id}")
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'message' => 'This size is used by product variants and cannot be deleted.',
        ]);

    $this->assertDatabaseHas('sizes', ['id' => $size->id]);
});

test('missing sizes return 404', function () {
    actingAsSizeStaff('products.edit');

    $this->putJson('/api/v1/admin/sizes/999', ['name' => 'M'])
        ->assertStatus(404);
    $this->deleteJson('/api/v1/admin/sizes/999')->assertStatus(404);
});

test('size mutations require the matching product permissions', function () {
    actingAsSizeStaff('products.view');

    $size = Size::factory()->create(['name' => 'M']);

    $this->postJson('/api/v1/admin/sizes', ['name' => 'L'])
        ->assertStatus(403);
    $this->putJson("/api/v1/admin/sizes/{$size->id}", ['name' => 'Large'])
        ->assertStatus(403);
    $this->deleteJson("/api/v1/admin/sizes/{$size->id}")
        ->assertStatus(403);
});

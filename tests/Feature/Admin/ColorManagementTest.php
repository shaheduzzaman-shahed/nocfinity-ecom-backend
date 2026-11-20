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

function actingAsColorStaff(string $permission): User
{
    $user = staffWithPermissions([$permission]);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

function variantUsingColor(Color $color): ProductVariant
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
        'color_id' => $color->id,
        'size_id' => Size::factory()->create()->id,
        'sku' => 'SKU-'.Str::upper(Str::random(10)),
        'stock' => 5,
        'is_active' => true,
    ]);
}

test('staff with products.view can list colors', function () {
    actingAsColorStaff('products.view');

    Color::factory()->create(['name' => 'Red', 'hex_code' => '#FF0000']);
    $black = Color::factory()->create(['name' => 'Black', 'hex_code' => '#000000']);
    variantUsingColor($black);

    $response = $this->getJson('/api/v1/admin/colors');

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Colors retrieved successfully.',
    ]);

    $data = $response->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['name'])->toBe('Black')
        ->and($data[0]['product_variants_count'])->toBe(1)
        ->and($data[1]['name'])->toBe('Red')
        ->and($data[1]['product_variants_count'])->toBe(0);
});

test('listing colors requires the products.view permission', function () {
    Sanctum::actingAs(staffWithPermissions(['categories.view']), ['admin']);

    $this->getJson('/api/v1/admin/colors')->assertStatus(403);
});

test('customers cannot manage colors', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/colors')->assertStatus(403);
});

test('staff with products.create can create a color', function () {
    actingAsColorStaff('products.create');

    $response = $this->postJson('/api/v1/admin/colors', [
        'name' => 'Navy Blue',
        'hex_code' => '#001f3f',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Navy Blue')
        ->assertJsonPath('data.hex_code', '#001F3F')
        ->assertJsonPath('data.product_variants_count', 0);

    $this->assertDatabaseHas('colors', [
        'name' => 'Navy Blue',
        'hex_code' => '#001F3F',
    ]);
});

test('a color can be created without a hex code', function () {
    actingAsColorStaff('products.create');

    $this->postJson('/api/v1/admin/colors', ['name' => 'Natural'])
        ->assertCreated()
        ->assertJsonPath('data.hex_code', null);
});

test('color names must be unique regardless of case', function () {
    actingAsColorStaff('products.create');

    Color::factory()->create(['name' => 'Navy']);

    $this->postJson('/api/v1/admin/colors', ['name' => 'navy'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('color hex codes must use the #RRGGBB format', function (string $hexCode) {
    actingAsColorStaff('products.create');

    $this->postJson('/api/v1/admin/colors', [
        'name' => 'Test Color',
        'hex_code' => $hexCode,
    ])->assertStatus(422)->assertJsonValidationErrors(['hex_code']);
})->with(['red', '#FFF', '#12345', '#1234567', '#GGGGGG']);

test('staff with products.edit can update a color', function () {
    actingAsColorStaff('products.edit');

    $color = Color::factory()->create([
        'name' => 'Navy',
        'hex_code' => '#001F3F',
    ]);

    $this->putJson("/api/v1/admin/colors/{$color->id}", [
        'name' => 'Deep Navy',
        'hex_code' => '#000080',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Deep Navy')
        ->assertJsonPath('data.hex_code', '#000080');

    $this->assertDatabaseHas('colors', [
        'id' => $color->id,
        'name' => 'Deep Navy',
        'hex_code' => '#000080',
    ]);
});

test('updating a color can keep its existing name', function () {
    actingAsColorStaff('products.edit');

    $color = Color::factory()->create(['name' => 'Navy', 'hex_code' => '#001F3F']);

    $this->putJson("/api/v1/admin/colors/{$color->id}", [
        'name' => 'navy',
        'hex_code' => '#111111',
    ])->assertOk()->assertJsonPath('data.hex_code', '#111111');
});

test('updating a color rejects a name used by another color', function () {
    actingAsColorStaff('products.edit');

    $color = Color::factory()->create(['name' => 'Navy']);
    Color::factory()->create(['name' => 'Red']);

    $this->putJson("/api/v1/admin/colors/{$color->id}", ['name' => 'red'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('staff with products.delete can delete an unused color', function () {
    actingAsColorStaff('products.delete');

    $color = Color::factory()->create(['name' => 'Temporary']);

    $this->deleteJson("/api/v1/admin/colors/{$color->id}")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Color deleted successfully.',
        ]);

    $this->assertDatabaseMissing('colors', ['id' => $color->id]);
});

test('a color used by a product variant cannot be deleted', function () {
    actingAsColorStaff('products.delete');

    $color = Color::factory()->create(['name' => 'Black']);
    variantUsingColor($color);

    $this->deleteJson("/api/v1/admin/colors/{$color->id}")
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'message' => 'This color is used by product variants and cannot be deleted.',
        ]);

    $this->assertDatabaseHas('colors', ['id' => $color->id]);
});

test('missing colors return 404', function () {
    actingAsColorStaff('products.edit');

    $this->putJson('/api/v1/admin/colors/999', ['name' => 'Navy'])
        ->assertStatus(404);
    $this->deleteJson('/api/v1/admin/colors/999')->assertStatus(404);
});

test('color mutations require the matching product permissions', function () {
    actingAsColorStaff('products.view');

    $color = Color::factory()->create(['name' => 'Navy']);

    $this->postJson('/api/v1/admin/colors', ['name' => 'New Color'])
        ->assertStatus(403);
    $this->putJson("/api/v1/admin/colors/{$color->id}", ['name' => 'Renamed'])
        ->assertStatus(403);
    $this->deleteJson("/api/v1/admin/colors/{$color->id}")
        ->assertStatus(403);
});

<?php

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    AppSetting::query()->updateOrCreate(
        ['setting_key' => 'store_currency'],
        ['setting_value' => 'USD', 'group_name' => 'currency'],
    );
    AppSetting::query()->updateOrCreate(
        ['setting_key' => 'shipping_fee'],
        ['setting_value' => '5.00', 'group_name' => 'shipping'],
    );
    AppSetting::query()->updateOrCreate(
        ['setting_key' => 'shipping_enabled'],
        ['setting_value' => '1', 'group_name' => 'shipping'],
    );
});

function createTestVariant(array $variantAttrs = [], array $productAttrs = []): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(array_merge([
        'regular_price' => 100.00,
        'discount_price' => 80.00,
        'is_active' => true,
    ], $productAttrs));

    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/sample.jpg',
    ]);

    $color = Color::firstOrCreate(['name' => 'Midnight Blue'], ['hex_code' => '#001133']);
    $size = Size::firstOrCreate(['name' => 'M'], ['sort_order' => 2]);

    return ProductVariant::factory()->for($product)->create(array_merge([
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'TEST-SKU-'.uniqid(),
        'stock' => 10,
        'price_override' => null,
        'is_active' => true,
    ], $variantAttrs));
}

test('guest or unauthenticated user cannot access customer cart endpoint', function () {
    $this->getJson('/api/v1/cart')
        ->assertUnauthorized();
});

test('staff account cannot access customer cart endpoint', function () {
    $staff = User::factory()->staff()->create();
    Sanctum::actingAs($staff, ['admin']);

    $this->getJson('/api/v1/cart')
        ->assertForbidden();
});

test('customer can view an empty cart with correct structure and summary', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $response = $this->getJson('/api/v1/cart');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.items_count', 0)
        ->assertJsonPath('data.total_quantity', 0)
        ->assertJsonPath('data.summary.subtotal', 0)
        ->assertJsonPath('data.summary.product_discount', 0)
        ->assertJsonPath('data.summary.shipping_estimate', 0)
        ->assertJsonPath('data.summary.final_total', 0)
        ->assertJsonPath('data.summary.currency', 'USD');
});

test('customer can add a product variant to cart', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createTestVariant(['stock' => 5], ['regular_price' => 100, 'discount_price' => 75]);

    $response = $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items_count', 1)
        ->assertJsonPath('data.total_quantity', 2)
        ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.unit_price', 75)
        ->assertJsonPath('data.items.0.line_subtotal', 150)
        ->assertJsonPath('data.summary.subtotal', 150)
        ->assertJsonPath('data.summary.product_discount', 50)
        ->assertJsonPath('data.summary.shipping_estimate', 5)
        ->assertJsonPath('data.summary.final_total', 155);

    $this->assertDatabaseHas('cart_items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);
});

test('adding an existing variant increments the quantity', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createTestVariant(['stock' => 10]);

    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertCreated();

    $response = $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 3,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.items_count', 1)
        ->assertJsonPath('data.total_quantity', 5)
        ->assertJsonPath('data.items.0.quantity', 5);

    $this->assertDatabaseHas('cart_items', [
        'product_variant_id' => $variant->id,
        'quantity' => 5,
    ]);
});

test('cannot add variant if requested quantity exceeds available stock', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createTestVariant(['stock' => 3]);

    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 4,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    // Also when combined quantity exceeds stock
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertCreated();

    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
});

test('cannot add inactive variant or variant belonging to inactive product', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $inactiveVariant = createTestVariant(['is_active' => false]);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $inactiveVariant->id,
        'quantity' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('product_variant_id');

    $inactiveProductVariant = createTestVariant([], ['is_active' => false]);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $inactiveProductVariant->id,
        'quantity' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('product_variant_id');
});

test('customer can update cart item quantity', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createTestVariant(['stock' => 10]);

    $addRes = $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);
    $itemId = $addRes->json('data.items.0.id');

    $response = $this->putJson("/api/v1/cart/items/{$itemId}", [
        'quantity' => 6,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.total_quantity', 6)
        ->assertJsonPath('data.items.0.quantity', 6);

    $this->assertDatabaseHas('cart_items', [
        'id' => $itemId,
        'quantity' => 6,
    ]);
});

test('cannot update quantity beyond available stock', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createTestVariant(['stock' => 5]);

    $addRes = $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);
    $itemId = $addRes->json('data.items.0.id');

    $this->putJson("/api/v1/cart/items/{$itemId}", [
        'quantity' => 6,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
});

test('cannot update or delete another customer cart item', function () {
    $customerA = User::factory()->create();
    $customerB = User::factory()->create();

    $variant = createTestVariant(['stock' => 10]);

    Sanctum::actingAs($customerA);
    $addRes = $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);
    $itemId = $addRes->json('data.items.0.id');

    // Acting as Customer B
    Sanctum::actingAs($customerB);

    $this->putJson("/api/v1/cart/items/{$itemId}", [
        'quantity' => 3,
    ])->assertNotFound();

    $this->deleteJson("/api/v1/cart/items/{$itemId}")
        ->assertNotFound();
});

test('customer can remove an item from cart', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createTestVariant(['stock' => 10]);

    $addRes = $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);
    $itemId = $addRes->json('data.items.0.id');

    $response = $this->deleteJson("/api/v1/cart/items/{$itemId}");

    $response->assertOk()
        ->assertJsonPath('data.items_count', 0)
        ->assertJsonPath('data.total_quantity', 0);

    $this->assertDatabaseMissing('cart_items', [
        'id' => $itemId,
    ]);
});

test('customer can clear their entire cart', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $varA = createTestVariant(['stock' => 10]);
    $varB = createTestVariant(['stock' => 10]);

    $this->postJson('/api/v1/cart/items', ['product_variant_id' => $varA->id, 'quantity' => 1]);
    $this->postJson('/api/v1/cart/items', ['product_variant_id' => $varB->id, 'quantity' => 2]);

    $response = $this->deleteJson('/api/v1/cart');

    $response->assertOk()
        ->assertJsonPath('data.items_count', 0)
        ->assertJsonPath('data.total_quantity', 0);

    $this->assertDatabaseCount('cart_items', 0);
});

test('guest cart merge aggregates matching variants and caps at available stock', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $varExisting = createTestVariant(['stock' => 5]);
    $varNew = createTestVariant(['stock' => 8]);
    $varOutOfStock = createTestVariant(['stock' => 0]);
    $varInactive = createTestVariant(['is_active' => false]);

    // Pre-populate customer's cart
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $varExisting->id,
        'quantity' => 3,
    ]);

    // Guest cart has:
    // - 4 of $varExisting (3 in cart + 4 guest = 7, but stock is 5 -> should cap at 5)
    // - 2 of $varNew (should insert 2)
    // - 1 of $varOutOfStock (should discard with warning)
    // - 1 of $varInactive (should discard with warning)
    $response = $this->postJson('/api/v1/cart/merge', [
        'items' => [
            ['product_variant_id' => $varExisting->id, 'quantity' => 4],
            ['product_variant_id' => $varNew->id, 'quantity' => 2],
            ['product_variant_id' => $varOutOfStock->id, 'quantity' => 1],
            ['product_variant_id' => $varInactive->id, 'quantity' => 1],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items_count', 2)
        ->assertJsonPath('data.total_quantity', 7); // 5 capped + 2 new = 7

    expect($response->json('data.warnings'))->not->toBeEmpty();

    $this->assertDatabaseHas('cart_items', [
        'product_variant_id' => $varExisting->id,
        'quantity' => 5,
    ]);

    $this->assertDatabaseHas('cart_items', [
        'product_variant_id' => $varNew->id,
        'quantity' => 2,
    ]);
});

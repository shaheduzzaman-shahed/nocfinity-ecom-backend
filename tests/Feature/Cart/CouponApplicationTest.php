<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
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

function createCouponVariant(float $price = 100.00): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'regular_price' => $price,
        'discount_price' => null,
        'is_active' => true,
    ]);

    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/sample.jpg',
    ]);

    $color = Color::firstOrCreate(['name' => 'Blue'], ['hex_code' => '#0000FF']);
    $size = Size::firstOrCreate(['name' => 'M'], ['sort_order' => 1]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'stock' => 50,
        'price_override' => null,
        'is_active' => true,
    ]);
}

test('guest cannot apply or remove coupons', function () {
    $this->postJson('/api/v1/coupons/apply', ['code' => 'SAVE10'])->assertUnauthorized();
    $this->deleteJson('/api/v1/coupons/remove')->assertUnauthorized();
});

test('applying coupon to empty cart fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    Coupon::create([
        'code' => 'EMPTY10',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'EMPTY10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('customer can apply valid percentage coupon', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(100.00);

    // Add 2 items = subtotal $200.00
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertCreated();

    Coupon::create([
        'code' => 'PCT10',
        'type' => 'percentage',
        'value' => 10, // 10%
        'min_order_amount' => 50.00,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/coupons/apply', ['code' => 'pct10'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.coupon.code', 'PCT10')
        ->assertJsonPath('data.coupon.type', 'percentage')
        ->assertJsonPath('data.summary.subtotal', fn ($v) => (float) $v === 200.0)
        ->assertJsonPath('data.summary.coupon_discount', fn ($v) => (float) $v === 20.0)
        ->assertJsonPath('data.summary.shipping_estimate', fn ($v) => (float) $v === 5.0)
        ->assertJsonPath('data.summary.final_total', fn ($v) => (float) $v === 185.0);
});

test('customer can apply valid fixed discount coupon and capped at subtotal', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);

    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'FIXED60',
        'type' => 'fixed',
        'value' => 60.00, // exceeds subtotal of 50
        'min_order_amount' => 0.00,
        'is_active' => true,
    ]);

    // Subtotal is 50, fixed coupon is 60 -> discount capped at min(value, subtotal) = 50
    $this->postJson('/api/v1/coupons/apply', ['code' => 'FIXED60'])
        ->assertOk()
        ->assertJsonPath('data.coupon.code', 'FIXED60')
        ->assertJsonPath('data.summary.subtotal', fn ($v) => (float) $v === 50.0)
        ->assertJsonPath('data.summary.coupon_discount', fn ($v) => (float) $v === 50.0)
        ->assertJsonPath('data.summary.final_total', fn ($v) => (float) $v === 5.0);
});

test('applying invalid or non-existent coupon fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->postJson('/api/v1/coupons/apply', ['code' => 'DOESNOTEXIST'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('applying inactive coupon fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'INACTIVE10',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'INACTIVE10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('applying coupon before valid_from fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'FUTURE10',
        'type' => 'percentage',
        'value' => 10,
        'valid_from' => now()->addDays(2),
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'FUTURE10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('applying expired coupon fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'EXPIRED10',
        'type' => 'percentage',
        'value' => 10,
        'valid_until' => now()->subDay(),
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'EXPIRED10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('applying coupon that reached usage limit fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'LIMITREACHED',
        'type' => 'percentage',
        'value' => 10,
        'usage_limit' => 5,
        'usage_count' => 5,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'LIMITREACHED'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('applying coupon when subtotal is below min_order_amount fails', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(30.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'MIN100',
        'type' => 'percentage',
        'value' => 20,
        'min_order_amount' => 100.00,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'MIN100'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('customer who already redeemed coupon cannot apply it again', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(50.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    $coupon = Coupon::create([
        'code' => 'ONETIME',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    $shippingAddress = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'address' => '123 Test St',
        'city' => 'Test City',
        'state' => 'Test State',
        'postal_code' => '12345',
        'country' => 'US',
    ]);

    // Create a mock order to fulfill order_id FK requirement
    $order = Order::create([
        'order_number' => 'ORD-2026-000001',
        'user_id' => $customer->id,
        'shipping_address_id' => $shippingAddress->id,
        'currency' => 'USD',
        'subtotal' => 50,
        'shipping_charge' => 5,
        'final_total' => 50,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'ship_name' => 'Test User',
        'ship_email' => 'test@example.com',
        'ship_phone' => '1234567890',
        'ship_address' => '123 Test St',
        'ship_city' => 'Test City',
        'ship_state' => 'Test State',
        'ship_postal_code' => '12345',
        'ship_country' => 'US',
    ]);

    // Record user redemption
    CouponUser::create([
        'coupon_id' => $coupon->id,
        'user_id' => $customer->id,
        'order_id' => $order->id,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'ONETIME'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('customer can remove applied coupon from cart', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $variant = createCouponVariant(100.00);
    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    Coupon::create([
        'code' => 'REMOVEME',
        'type' => 'percentage',
        'value' => 15,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/coupons/apply', ['code' => 'REMOVEME'])->assertOk();

    // Verify coupon is applied
    $getRes = $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.coupon.code', 'REMOVEME')
        ->assertJsonPath('data.summary.coupon_discount', fn ($v) => (float) $v === 15.0);

    // Remove coupon
    $this->deleteJson('/api/v1/coupons/remove')
        ->assertOk()
        ->assertJsonPath('data.coupon', null)
        ->assertJsonPath('data.summary.coupon_discount', fn ($v) => (float) $v === 0.0)
        ->assertJsonPath('data.summary.final_total', fn ($v) => (float) $v === 105.0); // 100 subtotal + 5 shipping

    // Verify cart now has no coupon
    $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.coupon', null)
        ->assertJsonPath('data.summary.coupon_discount', fn ($v) => (float) $v === 0.0);
});

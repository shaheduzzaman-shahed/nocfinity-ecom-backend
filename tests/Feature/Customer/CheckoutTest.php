<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Cart;
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

function createCheckoutVariant(array $variantAttrs = [], array $productAttrs = []): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Classic Oxford Shirt',
        'regular_price' => 100.00,
        'discount_price' => 80.00,
        'is_active' => true,
    ], $productAttrs));

    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/sample.jpg',
    ]);

    $color = Color::firstOrCreate(['name' => 'Navy Blue'], ['hex_code' => '#001133']);
    $size = Size::firstOrCreate(['name' => 'L'], ['sort_order' => 3]);

    return ProductVariant::factory()->for($product)->create(array_merge([
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'TEST-OXFORD-'.uniqid(),
        'stock' => 15,
        'price_override' => null,
        'is_active' => true,
    ], $variantAttrs));
}

function sampleAddressPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'phone' => '+1-555-0199',
        'address' => '742 Evergreen Terrace',
        'city' => 'Springfield',
        'state' => 'Oregon',
        'postal_code' => '97477',
        'country' => 'United States',
    ], $overrides);
}

test('guest cannot access checkout preview or submit checkout', function () {
    $this->getJson('/api/v1/checkout')->assertUnauthorized();
    $this->postJson('/api/v1/checkout', sampleAddressPayload())->assertUnauthorized();
});

test('customer cannot checkout with an empty cart', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/checkout', sampleAddressPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cart'])
        ->assertJsonPath('errors.cart.0', 'Your cart is empty. Please add products before checking out.');
});

test('customer cannot checkout if a product in cart is inactive', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant([], ['is_active' => false]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $this->postJson('/api/v1/checkout', sampleAddressPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cart']);
});

test('customer cannot checkout if a variant in cart is inactive', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant(['is_active' => false]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $this->postJson('/api/v1/checkout', sampleAddressPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cart']);
});

test('customer cannot checkout if requested quantity exceeds available stock', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant(['stock' => 2]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 5]);

    $this->postJson('/api/v1/checkout', sampleAddressPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cart']);
});

test('client-submitted pricing, totals, or discounts are strictly rejected', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant();
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $payloadWithFakeTotal = array_merge(sampleAddressPayload(), [
        'total' => 1.00,
        'subtotal' => 1.00,
        'final_total' => 1.00,
    ]);

    $this->postJson('/api/v1/checkout', $payloadWithFakeTotal)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['financials']);
});

test('checkout recalculates totals authoritatively from database records', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    // Product regular 100, discount 80. Variant price override 70.
    $variant = createCheckoutVariant(['price_override' => 70.00], ['regular_price' => 100.00]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    $response = $this->postJson('/api/v1/checkout', sampleAddressPayload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.subtotal', 200)
        ->assertJsonPath('data.product_discount', 60)
        ->assertJsonPath('data.net_subtotal', 140)
        ->assertJsonPath('data.shipping_charge', 5)
        ->assertJsonPath('data.final_total', 145);
});

test('checkout incorporates applied coupon discount and validates minimum order requirement', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant([], ['regular_price' => 100.00, 'discount_price' => 100.00]);
    $coupon = Coupon::factory()->percentage(20)->create([
        'code' => 'SAVE20',
        'min_order_amount' => 50.00,
        'is_active' => true,
    ]);

    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
    $cart->update(['coupon_id' => $coupon->id]);

    $response = $this->postJson('/api/v1/checkout', sampleAddressPayload());

    // 100 subtotal - 20 coupon + 5 shipping = 85
    $response->assertCreated()
        ->assertJsonPath('data.subtotal', 100)
        ->assertJsonPath('data.coupon_discount', 20)
        ->assertJsonPath('data.shipping_charge', 5)
        ->assertJsonPath('data.final_total', 85)
        ->assertJsonPath('data.coupon.code', 'SAVE20');
});

test('checkout rejects coupon if minimum order amount is not met', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant([], ['regular_price' => 40.00, 'discount_price' => 40.00]);
    $coupon = Coupon::factory()->percentage(10)->create([
        'code' => 'BIGSPENDER',
        'min_order_amount' => 100.00,
        'is_active' => true,
    ]);

    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
    $cart->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/v1/checkout', sampleAddressPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon']);
});

test('checkout rejects coupon if user has already redeemed it', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant([], ['regular_price' => 100.00, 'discount_price' => 100.00]);
    $coupon = Coupon::factory()->create(['code' => 'ONCEONLY', 'is_active' => true]);

    $previousOrder = Order::factory()->create(['user_id' => $customer->id]);

    // Record previous redemption
    CouponUser::create([
        'user_id' => $customer->id,
        'coupon_id' => $coupon->id,
        'order_id' => $previousOrder->id,
    ]);

    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
    $cart->update(['coupon_id' => $coupon->id]);

    $this->postJson('/api/v1/checkout', sampleAddressPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon']);
});

test('checkout honors disabled shipping configuration by charging zero shipping', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    AppSetting::query()->updateOrCreate(
        ['setting_key' => 'shipping_enabled'],
        ['setting_value' => '0', 'group_name' => 'shipping'],
    );

    $variant = createCheckoutVariant([], ['regular_price' => 100.00, 'discount_price' => 100.00]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $response = $this->postJson('/api/v1/checkout', sampleAddressPayload());

    $response->assertCreated()
        ->assertJsonPath('data.shipping_charge', 0)
        ->assertJsonPath('data.final_total', 100);
});

test('checkout customer can select an existing saved address with strict IDOR protection', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    Sanctum::actingAs($customer);

    $ownAddress = ShippingAddress::factory()->create(['user_id' => $customer->id]);
    $otherAddress = ShippingAddress::factory()->create(['user_id' => $otherCustomer->id]);

    $variant = createCheckoutVariant([], ['regular_price' => 50.00, 'discount_price' => 50.00]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    // Attempt IDOR: use other user's address
    $this->postJson('/api/v1/checkout', ['shipping_address_id' => $otherAddress->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_address_id']);

    // Valid own address
    $response = $this->postJson('/api/v1/checkout', ['shipping_address_id' => $ownAddress->id]);

    $response->assertCreated()
        ->assertJsonPath('data.shipping_address.id', $ownAddress->id)
        ->assertJsonPath('data.shipping_address.name', $ownAddress->name)
        ->assertJsonPath('data.shipping_address.address', $ownAddress->address);
});

test('checkout generates sequential order number matching ORD-YYYY-000001 pattern', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant([], ['regular_price' => 25.00, 'discount_price' => 25.00]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $response = $this->postJson('/api/v1/checkout', sampleAddressPayload());

    $year = now()->year;
    $response->assertCreated();
    $orderNumber = $response->json('data.order_number');
    expect($orderNumber)->toMatch("/^ORD-{$year}-\d{6}$/");
});

test('checkout creates immutable order_items snapshots and clears customer cart items', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createCheckoutVariant(['sku' => 'IMMUTABLE-SKU-123'], [
        'name' => 'Signature Wool Coat',
        'regular_price' => 250.00,
        'discount_price' => 200.00,
    ]);

    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    $response = $this->postJson('/api/v1/checkout', sampleAddressPayload());

    $response->assertCreated()
        ->assertJsonPath('data.items.0.product_name', 'Signature Wool Coat')
        ->assertJsonPath('data.items.0.sku', 'IMMUTABLE-SKU-123')
        ->assertJsonPath('data.items.0.unit_price', 200)
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.line_subtotal', 400);

    // Verify cart is now empty
    $cart->refresh();
    expect($cart->items()->count())->toBe(0);

    // Verify initial statuses are Pending
    $order = Order::where('user_id', $customer->id)->first();
    expect($order->order_status)->toBe(OrderStatus::Pending);
    expect($order->payment_status)->toBe(PaymentStatus::Pending);

    // Verify stock was NOT decremented yet (decrements only upon successful payment webhook)
    $variant->refresh();
    expect($variant->stock)->toBe(15);
});

test('customer can retrieve order confirmation by id or order_number with IDOR protection', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    Sanctum::actingAs($customer);

    $address = ShippingAddress::factory()->create(['user_id' => $customer->id]);
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'shipping_address_id' => $address->id,
        'order_number' => 'ORD-2026-000099',
        'final_total' => 150.00,
    ]);

    // Retrieve by ID
    $this->getJson("/api/v1/orders/{$order->id}/confirmation")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.order_number', 'ORD-2026-000099');

    // Retrieve by order number
    $this->getJson('/api/v1/orders/ORD-2026-000099/confirmation')
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);

    // Attempt retrieval by other customer
    Sanctum::actingAs($otherCustomer);
    $this->getJson("/api/v1/orders/{$order->id}/confirmation")
        ->assertNotFound();
});

test('customer can fetch checkout preview with accurate calculations and saved addresses', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    ShippingAddress::factory()->create(['user_id' => $customer->id, 'is_default' => true]);
    $variant = createCheckoutVariant([], ['regular_price' => 120.00, 'discount_price' => 100.00]);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $response = $this->getJson('/api/v1/checkout');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items_count', 1)
        ->assertJsonPath('data.summary.subtotal', 120)
        ->assertJsonPath('data.summary.product_discount', 20)
        ->assertJsonPath('data.summary.shipping_charge', 5)
        ->assertJsonPath('data.summary.final_total', 105)
        ->assertJsonCount(1, 'data.addresses');
});

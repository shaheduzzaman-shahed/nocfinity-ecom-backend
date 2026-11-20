<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Size;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(AppSettingSeeder::class);
});

function actingAsShippingStaff(string $permission): User
{
    $user = staffWithPermissions([$permission]);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

test('guest cannot access shipping settings', function () {
    $this->getJson('/api/v1/admin/settings/shipping')->assertUnauthorized();
    $this->putJson('/api/v1/admin/settings/shipping', ['shipping_fee' => 10])->assertUnauthorized();
});

test('staff without shipping.view cannot view shipping settings', function () {
    $user = staffWithPermissions(['categories.view']);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/settings/shipping')->assertForbidden();
});

test('staff without shipping.manage cannot update shipping settings', function () {
    actingAsShippingStaff('shipping.view');

    $this->putJson('/api/v1/admin/settings/shipping', [
        'shipping_fee' => 15.00,
    ])->assertForbidden();
});

test('staff with shipping.view can retrieve current shipping configuration', function () {
    actingAsShippingStaff('shipping.view');

    $response = $this->getJson('/api/v1/admin/settings/shipping')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.shipping_fee', fn ($v) => (float) $v === 5.0)
        ->assertJsonPath('data.shipping_enabled', true);
});

test('staff with shipping.manage can update shipping fee and enabled state', function () {
    actingAsShippingStaff('shipping.manage');

    // Update fee
    $this->putJson('/api/v1/admin/settings/shipping', [
        'shipping_fee' => 12.50,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.shipping_fee', fn ($v) => (float) $v === 12.5)
        ->assertJsonPath('data.shipping_enabled', true);

    expect(
        (float) AppSetting::query()->where('setting_key', 'shipping_fee')->value('setting_value')
    )->toBe(12.50);

    // Disable shipping
    $this->putJson('/api/v1/admin/settings/shipping', [
        'shipping_enabled' => false,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.shipping_fee', fn ($v) => (float) $v === 12.5)
        ->assertJsonPath('data.shipping_enabled', false);

    expect(
        AppSetting::query()->where('setting_key', 'shipping_enabled')->value('setting_value')
    )->toBe('false');
});

test('shipping fee validation rejects negative numbers', function () {
    actingAsShippingStaff('shipping.manage');

    $this->putJson('/api/v1/admin/settings/shipping', [
        'shipping_fee' => -5.00,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_fee']);
});

test('shipping fee validation rejects non-numeric input', function () {
    actingAsShippingStaff('shipping.manage');

    $this->putJson('/api/v1/admin/settings/shipping', [
        'shipping_fee' => 'free',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_fee']);
});

test('updating shipping configuration does not alter existing historical orders', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);

    $address = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Historical Customer',
        'email' => 'hist@example.com',
        'phone' => '1234567890',
        'address' => '456 History Blvd',
        'city' => 'Past',
        'state' => 'CA',
        'postal_code' => '90210',
        'country' => 'US',
    ]);

    // Create an order with original $5.00 shipping snapshot
    $order = Order::create([
        'order_number' => 'ORD-2026-000099',
        'user_id' => $customer->id,
        'shipping_address_id' => $address->id,
        'currency' => 'USD',
        'subtotal' => 100.00,
        'shipping_charge' => 5.00,
        'final_total' => 105.00,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'ship_name' => 'Historical Customer',
        'ship_email' => 'hist@example.com',
        'ship_phone' => '1234567890',
        'ship_address' => '456 History Blvd',
        'ship_city' => 'Past',
        'ship_state' => 'CA',
        'ship_postal_code' => '90210',
        'ship_country' => 'US',
    ]);

    // Admin changes shipping fee to $25.00
    actingAsShippingStaff('shipping.manage');
    $this->putJson('/api/v1/admin/settings/shipping', [
        'shipping_fee' => 25.00,
    ])->assertOk();

    // Verify existing order still has original $5.00 shipping charge
    $order->refresh();
    expect((float) $order->shipping_charge)->toBe(5.00);
    expect((float) $order->final_total)->toBe(105.00);

    // Verify subsequent cart calculation uses the new $25.00 shipping fee
    Sanctum::actingAs($customer);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'regular_price' => 50.00,
        'discount_price' => null,
        'is_active' => true,
    ]);
    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/sample.jpg',
    ]);
    $color = Color::firstOrCreate(['name' => 'Navy'], ['hex_code' => '#000080']);
    $size = Size::firstOrCreate(['name' => 'L'], ['sort_order' => 3]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'stock' => 10,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    $cartResponse = $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.summary.shipping_estimate', fn ($v) => (float) $v === 25.0)
        ->assertJsonPath('data.summary.final_total', fn ($v) => (float) $v === 75.0); // 50 subtotal + 25 shipping
});

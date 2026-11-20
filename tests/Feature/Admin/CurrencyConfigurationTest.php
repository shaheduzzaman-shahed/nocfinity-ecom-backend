<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShippingAddress;
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

function actingAsCurrencyStaff(string $permission): User
{
    $user = staffWithPermissions([$permission]);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

test('guest cannot access admin currency settings', function () {
    $this->getJson('/api/v1/admin/settings/currency')->assertUnauthorized();
    $this->putJson('/api/v1/admin/settings/currency', ['currency' => 'EUR'])->assertUnauthorized();
});

test('staff without currency.view cannot view currency settings', function () {
    $user = staffWithPermissions(['categories.view']);
    Sanctum::actingAs($user, ['admin']);

    $this->getJson('/api/v1/admin/settings/currency')->assertForbidden();
});

test('staff without currency.manage cannot update currency settings', function () {
    actingAsCurrencyStaff('currency.view');

    $this->putJson('/api/v1/admin/settings/currency', [
        'currency' => 'EUR',
    ])->assertForbidden();
});

test('staff with currency.view can retrieve current store currency and supported list', function () {
    actingAsCurrencyStaff('currency.view');

    $response = $this->getJson('/api/v1/admin/settings/currency')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.currency', 'USD');

    expect($response->json('data.supported_currencies'))->toBeArray();
    expect(count($response->json('data.supported_currencies')))->toBeGreaterThan(10);
});

test('staff with currency.manage can update store currency to supported Stripe currencies', function () {
    actingAsCurrencyStaff('currency.manage');

    // Update to EUR
    $this->putJson('/api/v1/admin/settings/currency', [
        'currency' => 'eur',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.currency', 'EUR');

    expect(
        AppSetting::query()->where('setting_key', 'store_currency')->value('setting_value')
    )->toBe('EUR');

    // Update to BDT
    $this->putJson('/api/v1/admin/settings/currency', [
        'currency' => 'BDT',
    ])
        ->assertOk()
        ->assertJsonPath('data.currency', 'BDT');

    expect(
        AppSetting::query()->where('setting_key', 'store_currency')->value('setting_value')
    )->toBe('BDT');
});

test('currency update rejects invalid format', function () {
    actingAsCurrencyStaff('currency.manage');

    // Too short
    $this->putJson('/api/v1/admin/settings/currency', ['currency' => 'US'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);

    // Too long
    $this->putJson('/api/v1/admin/settings/currency', ['currency' => 'USDT'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);

    // Non-alpha
    $this->putJson('/api/v1/admin/settings/currency', ['currency' => '123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

test('currency update rejects currencies not supported by Stripe', function () {
    actingAsCurrencyStaff('currency.manage');

    // XYZ is 3 letters but not a Stripe supported currency
    $this->putJson('/api/v1/admin/settings/currency', ['currency' => 'XYZ'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency'])
        ->assertJsonPath('errors.currency.0', 'The selected currency is not supported by Stripe.');
});

test('public endpoint allows unauthenticated retrieval of current store currency', function () {
    $this->getJson('/api/v1/settings/currency')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.currency', 'USD');
});

test('changing store currency does not modify historical orders or amounts', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);

    $address = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '1234567890',
        'address' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US',
    ]);

    // Create an order in USD
    $order = Order::create([
        'order_number' => 'ORD-2026-000555',
        'user_id' => $customer->id,
        'shipping_address_id' => $address->id,
        'currency' => 'USD',
        'subtotal' => 150.00,
        'shipping_charge' => 5.00,
        'final_total' => 155.00,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'ship_name' => 'John Doe',
        'ship_email' => 'john@example.com',
        'ship_phone' => '1234567890',
        'ship_address' => '123 Main St',
        'ship_city' => 'New York',
        'ship_state' => 'NY',
        'ship_postal_code' => '10001',
        'ship_country' => 'US',
    ]);

    // Admin changes currency to GBP
    actingAsCurrencyStaff('currency.manage');
    $this->putJson('/api/v1/admin/settings/currency', ['currency' => 'GBP'])->assertOk();

    // Verify historical order currency remains USD and amount remains 155.00
    $order->refresh();
    expect($order->currency)->toBe('USD');
    expect((float) $order->final_total)->toBe(155.00);

    // Verify product card and cart endpoints now reflect GBP
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'regular_price' => 75.00,
        'is_active' => true,
    ]);
    ProductImage::factory()->for($product)->primary()->create([
        'image_url' => 'products/sample.jpg',
    ]);

    $prodRes = $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.currency', 'GBP');

    Sanctum::actingAs($customer);
    $cartRes = $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.summary.currency', 'GBP');
});

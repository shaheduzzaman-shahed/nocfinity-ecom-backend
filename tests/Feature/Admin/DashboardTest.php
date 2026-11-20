<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Size;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

function createDashboardTestVariant(int $stock = 10, bool $productActive = true): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['is_active' => $productActive]);
    $color = Color::factory()->create();
    $size = Size::factory()->create();

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'DASH-SKU-'.uniqid(),
        'stock' => $stock,
    ]);
}

it('allows super admin to retrieve dashboard statistics', function () {
    $superAdmin = superAdminUser();
    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'today_orders',
                'today_revenue',
                'total_customers',
                'low_stock_count',
                'store_currency',
            ],
        ]);
});

it('allows staff with dashboard.view permission to access dashboard', function () {
    $staff = staffWithPermissions(['dashboard.view']);
    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');
    $response->assertOk();
});

it('allows staff with reports.view permission to access dashboard', function () {
    $staff = staffWithPermissions(['reports.view']);
    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');
    $response->assertOk();
});

it('denies staff without dashboard or reports permissions', function () {
    $staff = staffWithPermissions(['orders.view']);
    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');
    $response->assertForbidden();
});

it('denies customer accounts from accessing dashboard endpoints', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer, ['customer']);

    $response = $this->getJson('/api/v1/admin/dashboard');
    $response->assertForbidden();
});

it('denies unauthenticated guests from accessing dashboard endpoints', function () {
    $response = $this->getJson('/api/v1/admin/dashboard');
    $response->assertUnauthorized();
});

it('accurately computes today paid orders and revenue', function () {
    $superAdmin = superAdminUser();
    $customer = User::factory()->customer()->create();
    $address = ShippingAddress::factory()->for($customer)->create();

    // 1 Paid order created today for 120.50
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'final_total' => 120.50,
        'created_at' => Carbon::now(),
    ]);

    // 1 Delivered order created today for 80.00
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Delivered,
        'payment_status' => PaymentStatus::Paid,
        'final_total' => 80.00,
        'created_at' => Carbon::now(),
    ]);

    // 1 Pending unpaid order created today for 50.00 (MUST be excluded)
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'final_total' => 50.00,
        'created_at' => Carbon::now(),
    ]);

    // 1 Paid order created YESTERDAY for 200.00 (MUST be excluded from today stats)
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'final_total' => 200.00,
        'created_at' => Carbon::yesterday(),
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'today_orders' => 2,
                'today_revenue' => 200.50,
            ],
        ]);
});

it('accurately counts registered customers excluding staff accounts', function () {
    $superAdmin = superAdminUser();
    User::factory()->customer()->count(4)->create();
    User::factory()->staff()->count(3)->create();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.total_customers', 4);
});

it('accurately counts low-stock variants on active products', function () {
    $superAdmin = superAdminUser();

    // 2 active variants with stock <= 5
    createDashboardTestVariant(3, true);
    createDashboardTestVariant(0, true);

    // 1 active variant with stock = 10 (not low stock)
    createDashboardTestVariant(10, true);

    // 1 inactive variant with stock = 2 (excluded because product is inactive)
    createDashboardTestVariant(2, false);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.low_stock_count', 2);
});

it('returns trailing 30-day sales chart points with summary', function () {
    $superAdmin = superAdminUser();
    $customer = User::factory()->customer()->create();
    $address = ShippingAddress::factory()->for($customer)->create();

    // Order 5 days ago for 100.00
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'final_total' => 100.00,
        'created_at' => Carbon::today()->subDays(5),
    ]);

    // Order 2 days ago for 150.00
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'final_total' => 150.00,
        'created_at' => Carbon::today()->subDays(2),
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard/sales?days=30');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'period_days' => 30,
                'summary' => [
                    'total_revenue' => 250.00,
                    'total_orders' => 2,
                    'average_order_value' => 125.00,
                ],
            ],
        ]);

    $points = $response->json('data.points');
    expect(count($points))->toBe(30);
});

it('returns recent orders in descending order', function () {
    $superAdmin = superAdminUser();
    $customer = User::factory()->customer()->create(['name' => 'Recent Buyer']);
    $address = ShippingAddress::factory()->for($customer)->create();

    $olderOrder = Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_number' => 'ORD-2026-000001',
        'created_at' => Carbon::now()->subHours(2),
    ]);

    $newerOrder = Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_number' => 'ORD-2026-000002',
        'created_at' => Carbon::now(),
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/dashboard/recent-orders?limit=5');

    $response->assertOk();
    $data = $response->json('data');

    expect(count($data))->toBe(2)
        ->and($data[0]['order_number'])->toBe('ORD-2026-000002')
        ->and($data[1]['order_number'])->toBe('ORD-2026-000001');
});

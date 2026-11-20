<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Size;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

function createAdminOrderTestVariant(int $stock = 10): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['is_active' => true]);
    $color = Color::factory()->create();
    $size = Size::factory()->create();

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'ADMIN-SKU-'.uniqid(),
        'stock' => $stock,
    ]);
}

function createAdminTestOrder(
    ?User $customer = null,
    OrderStatus $status = OrderStatus::Pending,
    array $overrides = []
): Order {
    $customer ??= User::factory()->customer()->create();
    $address = ShippingAddress::factory()->for($customer)->create();
    $variant = createAdminOrderTestVariant(10);

    $order = Order::factory()->create(array_merge([
        'user_id' => $customer->id,
        'shipping_address_id' => $address->id,
        'order_number' => 'ORD-2026-'.str_pad((string) rand(1, 99999), 6, '0', STR_PAD_LEFT),
        'order_status' => $status,
        'payment_status' => in_array($status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered], true)
            ? PaymentStatus::Paid
            : PaymentStatus::Pending,
        'subtotal' => 120.00,
        'product_discount' => 0.00,
        'coupon_discount' => 0.00,
        'shipping_charge' => 10.00,
        'final_total' => 130.00,
        'ship_name' => $address->name,
        'ship_email' => 'customer@example.com',
        'ship_phone' => $address->phone,
        'ship_address' => $address->address,
        'ship_city' => $address->city,
        'ship_state' => $address->state,
        'ship_postal_code' => $address->postal_code,
        'ship_country' => $address->country,
    ], $overrides));

    OrderItem::create([
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'product_name' => $variant->product->name,
        'image_url' => 'products/sample.jpg',
        'sku' => $variant->sku,
        'color_name' => $variant->color->name,
        'size_name' => $variant->size->name,
        'unit_price' => 60.00,
        'quantity' => 2,
        'line_subtotal' => 120.00,
    ]);

    return $order->fresh(['items', 'shippingAddress', 'user']);
}

test('guest cannot access admin order endpoints', function () {
    $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
    $this->getJson('/api/v1/admin/orders/1')->assertUnauthorized();
    $this->patchJson('/api/v1/admin/orders/1/status', ['status' => 'processing'])->assertUnauthorized();
});

test('customer cannot access admin order endpoints', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer, ['customer']);

    $this->getJson('/api/v1/admin/orders')->assertForbidden();
    $this->getJson('/api/v1/admin/orders/1')->assertForbidden();
    $this->patchJson('/api/v1/admin/orders/1/status', ['status' => 'processing'])->assertForbidden();
});

test('staff without orders.view cannot list or view orders', function () {
    $staff = staffWithPermissions(['products.view']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder();

    $this->getJson('/api/v1/admin/orders')->assertForbidden();
    $this->getJson("/api/v1/admin/orders/{$order->id}")->assertForbidden();
});

test('staff with orders.view can list orders with pagination', function () {
    $staff = staffWithPermissions(['orders.view']);
    Sanctum::actingAs($staff, ['admin']);

    createAdminTestOrder(null, OrderStatus::Pending);
    createAdminTestOrder(null, OrderStatus::Paid);

    $response = $this->getJson('/api/v1/admin/orders')
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'customer' => ['id', 'name', 'email'],
                    'order_status',
                    'payment_status',
                    'permitted_transitions',
                    'final_total',
                    'items_count',
                ],
            ],
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);

    expect($response->json('meta.total'))->toBe(2);
});

test('staff can search orders by order number, customer name, and email', function () {
    $staff = staffWithPermissions(['orders.view']);
    Sanctum::actingAs($staff, ['admin']);

    $customer = User::factory()->customer()->create([
        'name' => 'Alice Wonderland',
        'email' => 'alice@wonderland.com',
    ]);

    $targetOrder = createAdminTestOrder($customer, OrderStatus::Paid, [
        'order_number' => 'ORD-2026-999888',
        'ship_name' => 'Alice Wonderland',
    ]);
    createAdminTestOrder(null, OrderStatus::Pending);

    // Search by order number
    $byNumber = $this->getJson('/api/v1/admin/orders?search=999888')->assertStatus(200);
    expect($byNumber->json('meta.total'))->toBe(1)
        ->and($byNumber->json('data.0.order_number'))->toBe('ORD-2026-999888');

    // Search by customer name
    $byName = $this->getJson('/api/v1/admin/orders?search=Alice')->assertStatus(200);
    expect($byName->json('meta.total'))->toBe(1)
        ->and($byName->json('data.0.customer.name'))->toBe('Alice Wonderland');

    // Search by customer email
    $byEmail = $this->getJson('/api/v1/admin/orders?search=alice@wonderland.com')->assertStatus(200);
    expect($byEmail->json('meta.total'))->toBe(1);
});

test('staff can filter orders by status', function () {
    $staff = staffWithPermissions(['orders.view']);
    Sanctum::actingAs($staff, ['admin']);

    createAdminTestOrder(null, OrderStatus::Pending);
    createAdminTestOrder(null, OrderStatus::Paid);
    createAdminTestOrder(null, OrderStatus::Processing);

    $pendingResponse = $this->getJson('/api/v1/admin/orders?status=pending')->assertStatus(200);
    expect($pendingResponse->json('meta.total'))->toBe(1);

    $paidResponse = $this->getJson('/api/v1/admin/orders?status=paid')->assertStatus(200);
    expect($paidResponse->json('meta.total'))->toBe(1);
});

test('staff can view single order details with customer and permitted transitions', function () {
    $staff = staffWithPermissions(['orders.view']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Paid);

    $response = $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.order_status', 'paid')
        ->assertJsonPath('data.permitted_transitions', ['processing', 'cancelled']);
});

test('staff without orders.update_status cannot update status', function () {
    $staff = staffWithPermissions(['orders.view']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Paid);

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'processing',
    ])->assertForbidden();
});

test('staff can transition order from paid to processing and sends notification email', function () {
    Mail::fake();

    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Paid);

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'processing',
        'note' => 'Items are currently being picked and packed.',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'processing')
        ->assertJsonPath('data.permitted_transitions', ['shipped', 'cancelled']);

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Processing);

    Mail::assertSent(OrderStatusUpdatedMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id && $mail->newStatus === 'processing';
    });
});

test('staff can transition order from processing to shipped and sends notification email', function () {
    Mail::fake();

    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Processing);

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'shipped',
        'note' => 'Tracking: DHL-123456789',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'shipped')
        ->assertJsonPath('data.permitted_transitions', ['delivered']);

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Shipped);

    Mail::assertSent(OrderStatusUpdatedMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id && $mail->newStatus === 'shipped';
    });
});

test('staff can transition order from shipped to delivered and sends notification email', function () {
    Mail::fake();

    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Shipped);

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'delivered',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'delivered')
        ->assertJsonPath('data.permitted_transitions', []);

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Delivered);

    Mail::assertSent(OrderStatusUpdatedMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id && $mail->newStatus === 'delivered';
    });
});

test('staff can cancel pending order without restoring stock', function () {
    Mail::fake();

    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Pending);
    $item = $order->items->first();
    $variant = $item->productVariant;
    $initialStock = $variant->stock;

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'cancelled',
        'reason' => 'Customer requested cancellation via phone.',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'cancelled');

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull();

    // Stock must not change
    $variant->refresh();
    expect($variant->stock)->toBe($initialStock);

    Mail::assertSent(OrderCancelledMail::class);
});

test('staff can cancel paid order with transactional stock restoration', function () {
    Mail::fake();

    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Paid);
    $item = $order->items->first();
    $variant = $item->productVariant;
    $initialStock = $variant->stock;

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'cancelled',
        'reason' => 'Item defective before dispatch.',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'cancelled');

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull();

    // Stock must be restored: initial + item quantity
    $variant->refresh();
    expect($variant->stock)->toBe($initialStock + $item->quantity);

    Mail::assertSent(OrderCancelledMail::class);
});

test('staff can cancel processing order with transactional stock restoration', function () {
    Mail::fake();

    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $order = createAdminTestOrder(null, OrderStatus::Processing);
    $item = $order->items->first();
    $variant = $item->productVariant;
    $initialStock = $variant->stock;

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'cancelled',
        'reason' => 'Packing issue, cancelled by warehouse supervisor.',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'cancelled');

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull();

    // Stock restored
    $variant->refresh();
    expect($variant->stock)->toBe($initialStock + $item->quantity);

    Mail::assertSent(OrderCancelledMail::class);
});

test('staff cannot cancel shipped or delivered orders', function () {
    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $shippedOrder = createAdminTestOrder(null, OrderStatus::Shipped);
    $this->patchJson("/api/v1/admin/orders/{$shippedOrder->id}/status", [
        'status' => 'cancelled',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);

    $deliveredOrder = createAdminTestOrder(null, OrderStatus::Delivered);
    $this->patchJson("/api/v1/admin/orders/{$deliveredOrder->id}/status", [
        'status' => 'cancelled',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);
});

test('staff cannot modify already cancelled or delivered orders', function () {
    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $cancelledOrder = createAdminTestOrder(null, OrderStatus::Cancelled);
    $this->patchJson("/api/v1/admin/orders/{$cancelledOrder->id}/status", [
        'status' => 'processing',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);

    $deliveredOrder = createAdminTestOrder(null, OrderStatus::Delivered);
    $this->patchJson("/api/v1/admin/orders/{$deliveredOrder->id}/status", [
        'status' => 'processing',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);
});

test('staff cannot manually update order status to paid', function () {
    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $pendingOrder = createAdminTestOrder(null, OrderStatus::Pending);

    // Validation rule rejects 'paid'
    $this->patchJson("/api/v1/admin/orders/{$pendingOrder->id}/status", [
        'status' => 'paid',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);
});

test('staff cannot jump stages or perform invalid lifecycle transitions', function () {
    $staff = staffWithPermissions(['orders.view', 'orders.update_status']);
    Sanctum::actingAs($staff, ['admin']);

    $paidOrder = createAdminTestOrder(null, OrderStatus::Paid);

    // Cannot jump from Paid directly to Delivered or Shipped
    $this->patchJson("/api/v1/admin/orders/{$paidOrder->id}/status", [
        'status' => 'delivered',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);

    $this->patchJson("/api/v1/admin/orders/{$paidOrder->id}/status", [
        'status' => 'shipped',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);
});

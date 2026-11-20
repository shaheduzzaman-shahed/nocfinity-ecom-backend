<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderCancelledMail;
use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createCustomerOrderTestVariant(int $stock = 10): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['is_active' => true]);
    $color = Color::factory()->create();
    $size = Size::factory()->create();

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'TEST-SKU-'.uniqid(),
        'stock' => $stock,
    ]);
}

function createCustomerTestOrder(User $user, OrderStatus $status = OrderStatus::Pending, array $overrides = []): Order
{
    $address = ShippingAddress::factory()->for($user)->create();
    $variant = createCustomerOrderTestVariant(10);

    $order = Order::factory()->create(array_merge([
        'user_id' => $user->id,
        'shipping_address_id' => $address->id,
        'order_number' => 'ORD-2026-'.str_pad((string) rand(1, 99999), 6, '0', STR_PAD_LEFT),
        'order_status' => $status,
        'payment_status' => $status === OrderStatus::Paid ? PaymentStatus::Paid : PaymentStatus::Pending,
        'subtotal' => 100.00,
        'product_discount' => 0.00,
        'coupon_discount' => 0.00,
        'shipping_charge' => 10.00,
        'final_total' => 110.00,
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
        'color_name' => 'Navy Blue',
        'size_name' => 'M',
        'unit_price' => 50.00,
        'quantity' => 2,
        'line_subtotal' => 100.00,
    ]);

    return $order->fresh(['items', 'shippingAddress']);
}

test('guest cannot access customer order endpoints', function () {
    $this->getJson('/api/v1/orders')->assertStatus(401);
    $this->getJson('/api/v1/orders/1')->assertStatus(401);
    $this->postJson('/api/v1/orders/1/cancel')->assertStatus(401);
});

test('customer can list their own orders with pagination', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $order1 = createCustomerTestOrder($customer, OrderStatus::Pending);
    $order2 = createCustomerTestOrder($customer, OrderStatus::Paid);

    $response = $this->getJson('/api/v1/orders')
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Customer orders retrieved successfully.',
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'order_status',
                    'payment_status',
                    'can_cancel',
                    'final_total',
                    'items',
                ],
            ],
            'meta' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
            ],
        ]);

    expect($response->json('meta.total'))->toBe(2);
});

test('customer cannot see orders belonging to other customers (IDOR)', function () {
    $customerA = User::factory()->customer()->create();
    $customerB = User::factory()->customer()->create();

    $orderB = createCustomerTestOrder($customerB, OrderStatus::Paid);

    Sanctum::actingAs($customerA);

    // List should not include customer B's order
    $listResponse = $this->getJson('/api/v1/orders')->assertStatus(200);
    expect($listResponse->json('meta.total'))->toBe(0);

    // Direct detail view should 404
    $this->getJson("/api/v1/orders/{$orderB->id}")->assertStatus(404);
    $this->getJson("/api/v1/orders/{$orderB->order_number}")->assertStatus(404);

    // Cancel attempt should 404
    $this->postJson("/api/v1/orders/{$orderB->id}/cancel")->assertStatus(404);
});

test('customer can view order details by numeric id or order number', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $order = createCustomerTestOrder($customer, OrderStatus::Pending);

    // By ID
    $byId = $this->getJson("/api/v1/orders/{$order->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.can_cancel', true);

    // By order number
    $byNumber = $this->getJson("/api/v1/orders/{$order->order_number}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.can_cancel', true);
});

test('customer can cancel a pending order without stock restoration', function () {
    Mail::fake();

    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $order = createCustomerTestOrder($customer, OrderStatus::Pending);
    $item = $order->items->first();
    $variant = $item->productVariant;
    $initialStock = $variant->stock;

    $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [
        'reason' => 'Changed my mind',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'cancelled')
        ->assertJsonPath('data.can_cancel', false);

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelled_at)->not->toBeNull();

    // Stock must not change since pending order never decremented stock
    $variant->refresh();
    expect($variant->stock)->toBe($initialStock);

    Mail::assertSent(OrderCancelledMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id && $mail->hasTo('customer@example.com');
    });
});

test('customer can cancel a paid order with automatic stock restoration', function () {
    Mail::fake();

    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $order = createCustomerTestOrder($customer, OrderStatus::Paid);
    $item = $order->items->first();
    $variant = $item->productVariant;
    $initialStock = $variant->stock; // 10

    $response = $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertStatus(200)
        ->assertJsonPath('data.order_status', 'cancelled')
        ->assertJsonPath('data.can_cancel', false);

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelled_at)->not->toBeNull();

    // Stock must be restored: 10 + 2 = 12
    $variant->refresh();
    expect($variant->stock)->toBe($initialStock + $item->quantity);

    Mail::assertSent(OrderCancelledMail::class);
});

test('customer cannot cancel an order that is processing', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $order = createCustomerTestOrder($customer, OrderStatus::Processing);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Processing);
});

test('customer cannot cancel an order that is shipped or delivered', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $shippedOrder = createCustomerTestOrder($customer, OrderStatus::Shipped);
    $this->postJson("/api/v1/orders/{$shippedOrder->id}/cancel")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    $deliveredOrder = createCustomerTestOrder($customer, OrderStatus::Delivered);
    $this->postJson("/api/v1/orders/{$deliveredOrder->id}/cancel")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('repeated cancellation requests are rejected and cannot duplicate stock restoration', function () {
    Mail::fake();

    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $order = createCustomerTestOrder($customer, OrderStatus::Paid);
    $item = $order->items->first();
    $variant = $item->productVariant;
    $initialStock = $variant->stock;

    // First cancellation succeeds
    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertStatus(200);

    $variant->refresh();
    expect($variant->stock)->toBe($initialStock + $item->quantity);

    // Second cancellation fails with 422
    $this->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    // Stock remains restored exactly once
    $variant->refresh();
    expect($variant->stock)->toBe($initialStock + $item->quantity);
});

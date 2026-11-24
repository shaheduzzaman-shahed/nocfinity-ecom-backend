<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmationMail;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Apparel',
        'slug' => 'apparel',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->color = Color::create(['name' => 'Black', 'hex_code' => '#000000']);
    $this->size = Size::create(['name' => 'M', 'sort_order' => 1]);

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Classic Tee',
        'slug' => 'classic-tee',
        'regular_price' => 50.00,
        'discount_price' => 45.00,
        'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'color_id' => $this->color->id,
        'size_id' => $this->size->id,
        'sku' => 'TSH-BLK-M',
        'stock' => 20,
        'is_active' => true,
    ]);

    $this->customer = User::factory()->create([
        'email' => 'customer@example.com',
        'name' => 'Jane Customer',
    ]);

    $this->shippingAddress = ShippingAddress::create([
        'user_id' => $this->customer->id,
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
        'phone' => '+15551234567',
        'address' => '123 Market St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'postal_code' => '94105',
        'country' => 'US',
        'is_default' => true,
    ]);

    $this->order = Order::create([
        'order_number' => 'ORD-2026-000101',
        'user_id' => $this->customer->id,
        'shipping_address_id' => $this->shippingAddress->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'currency' => 'USD',
        'subtotal' => 90.00,
        'product_discount' => 10.00,
        'coupon_discount' => 5.00,
        'shipping_charge' => 15.00,
        'final_total' => 90.00,
        'ship_name' => 'Jane Customer',
        'ship_email' => 'customer@example.com',
        'ship_phone' => '+15551234567',
        'ship_address' => '123 Market St',
        'ship_city' => 'San Francisco',
        'ship_state' => 'CA',
        'ship_postal_code' => '94105',
        'ship_country' => 'US',
        'paid_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $this->order->id,
        'product_variant_id' => $this->variant->id,
        'product_name' => 'Classic Tee',
        'image_url' => 'https://example.com/tee.jpg',
        'sku' => 'TSH-BLK-M',
        'color_name' => 'Black',
        'size_name' => 'M',
        'unit_price' => 45.00,
        'quantity' => 2,
        'line_subtotal' => 90.00,
    ]);
});

test('order confirmation email renders details and items correctly', function () {
    $mailable = new OrderConfirmationMail($this->order);

    $mailable->assertHasSubject("Order Confirmed - {$this->order->order_number}");

    $html = $mailable->render();

    expect($html)
        ->toContain('ORD-2026-000101')
        ->toContain('Classic Tee')
        ->toContain('TSH-BLK-M')
        ->toContain('Black')
        ->toContain('Jane Customer')
        ->toContain('123 Market St')
        ->toContain('USD 90.00')
        ->toContain('View Order Details');
});

test('order status updated email renders processing status correctly', function () {
    $mailable = new OrderStatusUpdatedMail(
        $this->order,
        OrderStatus::Paid->value,
        OrderStatus::Processing->value,
        'Items picked and being prepared'
    );

    $mailable->assertHasSubject("Order {$this->order->order_number} Update - Processing");

    $html = $mailable->render();

    expect($html)
        ->toContain('ORD-2026-000101')
        ->toContain('Processing')
        ->toContain('Items picked and being prepared')
        ->toContain('Track Order')
        ->toContain('Classic Tee');
});

test('order status updated email renders shipped status correctly', function () {
    $mailable = new OrderStatusUpdatedMail(
        $this->order,
        OrderStatus::Processing->value,
        OrderStatus::Shipped->value,
        'Carrier tracking # TRK-998877'
    );

    $mailable->assertHasSubject("Order {$this->order->order_number} Update - Shipped");

    $html = $mailable->render();

    expect($html)
        ->toContain('ORD-2026-000101')
        ->toContain('Shipped')
        ->toContain('TRK-998877')
        ->toContain('Track Order');
});

test('order status updated email renders delivered status correctly', function () {
    $mailable = new OrderStatusUpdatedMail(
        $this->order,
        OrderStatus::Shipped->value,
        OrderStatus::Delivered->value
    );

    $mailable->assertHasSubject("Order {$this->order->order_number} Update - Delivered");

    $html = $mailable->render();

    expect($html)
        ->toContain('ORD-2026-000101')
        ->toContain('Delivered')
        ->toContain('Your package has been successfully delivered');
});

test('order cancelled email renders cancellation reason and notice correctly', function () {
    $mailable = new OrderCancelledMail(
        $this->order,
        'Requested cancellation prior to dispatch'
    );

    $mailable->assertHasSubject("Order Cancelled - {$this->order->order_number}");

    $html = $mailable->render();

    expect($html)
        ->toContain('ORD-2026-000101')
        ->toContain('Order Cancelled')
        ->toContain('Requested cancellation prior to dispatch')
        ->toContain('Classic Tee')
        ->toContain('Continue Shopping');
});

test('emails can be sent via Laravel Mail facade to the customer address', function () {
    Mail::fake();

    Mail::to($this->order->ship_email)->send(new OrderConfirmationMail($this->order));

    Mail::assertSent(OrderConfirmationMail::class, function ($mail) {
        return $mail->hasTo('customer@example.com')
            && $mail->order->order_number === 'ORD-2026-000101';
    });
});

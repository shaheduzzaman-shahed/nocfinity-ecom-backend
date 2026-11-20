<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmationMail;
use App\Models\AppSetting;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
});

function createStripeTestVariant(int $stock = 10, float $price = 100.00): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'name' => 'Premium Cashmere Sweater',
        'regular_price' => $price,
        'discount_price' => $price,
        'is_active' => true,
    ]);

    ProductImage::factory()->for($product)->primary()->create();

    $color = Color::firstOrCreate(['name' => 'Charcoal'], ['hex_code' => '#333333']);
    $size = Size::firstOrCreate(['name' => 'M'], ['sort_order' => 2]);

    return ProductVariant::factory()->for($product)->create([
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'SWTR-CASH-'.uniqid(),
        'stock' => $stock,
        'is_active' => true,
    ]);
}

function createStripeWebhookPayload(string $eventType, string $intentId, array $overrides = []): string
{
    return json_encode([
        'id' => 'evt_test_'.uniqid(),
        'object' => 'event',
        'type' => $eventType,
        'data' => [
            'object' => array_merge([
                'id' => $intentId,
                'object' => 'payment_intent',
                'amount' => 10500,
                'currency' => 'usd',
                'status' => $eventType === 'payment_intent.succeeded' ? 'succeeded' : 'requires_payment_method',
                'latest_charge' => 'ch_test_'.uniqid(),
                'last_payment_error' => $eventType === 'payment_intent.payment_failed' ? [
                    'message' => 'Your card was declined.',
                ] : null,
                'metadata' => [
                    'test_key' => 'test_val',
                ],
            ], $overrides),
        ],
    ]);
}

test('checkout creates a Stripe PaymentIntent, client_secret, and a pending payments record', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $variant = createStripeTestVariant(10, 100.00);
    $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $address = ShippingAddress::factory()->create(['user_id' => $customer->id]);

    $response = $this->postJson('/api/v1/checkout', [
        'shipping_address_id' => $address->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $clientSecret = $response->json('data.client_secret');
    expect($clientSecret)->not->toBeEmpty();
    expect($clientSecret)->toContain('_secret_');

    $orderId = $response->json('data.id');
    $this->assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'payment_provider' => 'stripe',
        'payment_status' => 'pending',
        'amount' => 105.00,
        'currency' => 'USD',
    ]);
});

test('stripe webhook endpoint returns 400 when signature is invalid or missing', function () {
    $payload = createStripeWebhookPayload('payment_intent.succeeded', 'pi_test_123');

    // Missing signature header
    $this->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true))
        ->assertStatus(400)
        ->assertJsonPath('success', false);

    // Invalid signature header
    $this->withHeaders(['Stripe-Signature' => 'invalid_sig'])
        ->post('/api/v1/payments/stripe/webhook', [], ['CONTENT_TYPE' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('success', false);
});

test('webhook processes payment_intent.succeeded, transitions order to paid, deducts stock, and sends email', function () {
    Mail::fake();

    $customer = User::factory()->create();
    $variant = createStripeTestVariant(10, 100.00);

    $address = ShippingAddress::factory()->create(['user_id' => $customer->id]);
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'subtotal' => 100.00,
        'shipping_charge' => 5.00,
        'final_total' => 105.00,
        'ship_email' => 'customer@example.com',
    ]);

    $order->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => 'Premium Cashmere Sweater',
        'sku' => $variant->sku,
        'color_name' => 'Charcoal',
        'size_name' => 'M',
        'unit_price' => 100.00,
        'quantity' => 2,
        'line_subtotal' => 200.00,
    ]);

    $paymentIntentId = 'pi_test_'.uniqid();
    $payment = Payment::create([
        'order_id' => $order->id,
        'payment_provider' => 'stripe',
        'stripe_payment_intent_id' => $paymentIntentId,
        'amount' => 105.00,
        'currency' => 'USD',
        'payment_status' => 'pending',
    ]);

    $payload = createStripeWebhookPayload('payment_intent.succeeded', $paymentIntentId, [
        'latest_charge' => 'ch_test_charge_123',
    ]);

    $response = $this->call(
        'POST',
        '/api/v1/payments/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 'test_signature', 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertOk()
        ->assertJsonPath('success', true);

    // Verify order transitioned to paid
    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Paid);
    expect($order->payment_status)->toBe(PaymentStatus::Paid);
    expect($order->paid_at)->not->toBeNull();

    // Verify payment updated to succeeded with charge ID
    $payment->refresh();
    expect($payment->payment_status)->toBe('succeeded');
    expect($payment->stripe_charge_id)->toBe('ch_test_charge_123');

    // Verify stock was decremented from 10 to 8
    $variant->refresh();
    expect($variant->stock)->toBe(8);

    // Verify confirmation email was sent
    Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id && $mail->hasTo('customer@example.com');
    });
});

test('webhook handles payment_intent.succeeded with coupon tracking and per-user redemption', function () {
    $customer = User::factory()->create();
    $variant = createStripeTestVariant(5, 100.00);
    $coupon = Coupon::factory()->create([
        'code' => 'COUPON50',
        'usage_count' => 0,
        'is_active' => true,
    ]);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'coupon_id' => $coupon->id,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'subtotal' => 100.00,
        'final_total' => 55.00,
    ]);

    $order->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => 'Premium Cashmere Sweater',
        'sku' => $variant->sku,
        'color_name' => 'Charcoal',
        'size_name' => 'M',
        'unit_price' => 100.00,
        'quantity' => 1,
        'line_subtotal' => 100.00,
    ]);

    $intentId = 'pi_test_coupon_'.uniqid();
    Payment::create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => $intentId,
        'amount' => 55.00,
        'currency' => 'USD',
        'payment_status' => 'pending',
    ]);

    $payload = createStripeWebhookPayload('payment_intent.succeeded', $intentId);

    $this->call(
        'POST',
        '/api/v1/payments/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 'test_signature', 'CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertOk();

    // Verify coupon usage incremented
    $coupon->refresh();
    expect($coupon->usage_count)->toBe(1);

    // Verify coupon_user record created
    $this->assertDatabaseHas('coupon_user', [
        'coupon_id' => $coupon->id,
        'user_id' => $customer->id,
        'order_id' => $order->id,
    ]);
});

test('webhook execution is strictly idempotent (repeated events do not duplicate stock deductions)', function () {
    $customer = User::factory()->create();
    $variant = createStripeTestVariant(10, 100.00);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $order->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => 'Premium Cashmere Sweater',
        'sku' => $variant->sku,
        'color_name' => 'Charcoal',
        'size_name' => 'M',
        'unit_price' => 100.00,
        'quantity' => 3,
        'line_subtotal' => 300.00,
    ]);

    $intentId = 'pi_test_idempotent_'.uniqid();
    Payment::create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => $intentId,
        'amount' => 105.00,
        'currency' => 'USD',
        'payment_status' => 'pending',
    ]);

    $payload = createStripeWebhookPayload('payment_intent.succeeded', $intentId);

    // First webhook call: stock 10 -> 7
    $this->call(
        'POST',
        '/api/v1/payments/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 'test_signature', 'CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertOk();

    $variant->refresh();
    expect($variant->stock)->toBe(7);

    // Second webhook call (duplicate event from Stripe): stock MUST remain 7!
    $this->call(
        'POST',
        '/api/v1/payments/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 'test_signature', 'CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertOk();

    $variant->refresh();
    expect($variant->stock)->toBe(7);
});

test('webhook handles payment_intent.payment_failed by updating payment status without deducting inventory', function () {
    $customer = User::factory()->create();
    $variant = createStripeTestVariant(10, 100.00);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $order->items()->create([
        'product_variant_id' => $variant->id,
        'product_name' => 'Premium Cashmere Sweater',
        'sku' => $variant->sku,
        'color_name' => 'Charcoal',
        'size_name' => 'M',
        'unit_price' => 100.00,
        'quantity' => 2,
        'line_subtotal' => 200.00,
    ]);

    $intentId = 'pi_test_failed_'.uniqid();
    $payment = Payment::create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => $intentId,
        'amount' => 105.00,
        'currency' => 'USD',
        'payment_status' => 'pending',
    ]);

    $payload = createStripeWebhookPayload('payment_intent.payment_failed', $intentId, [
        'last_payment_error' => ['message' => 'Insufficient funds on credit card.'],
    ]);

    $this->call(
        'POST',
        '/api/v1/payments/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 'test_signature', 'CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertOk();

    // Verify payment record marked failed with error message
    $payment->refresh();
    expect($payment->payment_status)->toBe('failed');
    expect($payment->failure_reason)->toBe('Insufficient funds on credit card.');

    // Verify order payment status is failed, but status is NOT paid
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::Failed);
    expect($order->order_status)->toBe(OrderStatus::Pending);

    // Verify stock is preserved (NOT decremented)
    $variant->refresh();
    expect($variant->stock)->toBe(10);
});

test('payments stripe config endpoint exposes publishable key', function () {
    config(['services.stripe.key' => 'pk_test_sample_key_123']);

    $this->getJson('/api/v1/payments/stripe/config')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.publishable_key', 'pk_test_sample_key_123');
});

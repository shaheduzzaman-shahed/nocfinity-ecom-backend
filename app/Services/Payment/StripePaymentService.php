<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmationMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Support\StripeCurrencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentService
{
    /**
     * @return array{id: string, client_secret: string}
     */
    public function createPaymentIntent(Order $order): array
    {
        $secretKey = config('services.stripe.secret');

        // When in live test mode with configured credentials
        if ($secretKey && ! app()->environment('testing')) {
            $stripe = new StripeClient($secretKey);

            $intent = $stripe->paymentIntents->create([
                'amount' => StripeCurrencies::toStripeAmount((float) $order->final_total, $order->currency),
                'currency' => strtolower($order->currency),
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => (string) $order->user_id,
                ],
                'payment_method_types' => ['card'],
            ]);

            return [
                'id' => $intent->id,
                'client_secret' => $intent->client_secret,
            ];
        }

        // Mock test intent for automated testing or development without live Stripe keys
        $mockId = 'pi_test_'.uniqid();

        return [
            'id' => $mockId,
            'client_secret' => $mockId.'_secret_'.uniqid(),
        ];
    }

    /**
     * @throws SignatureVerificationException
     */
    public function verifyWebhookSignature(string $payload, string $signature): Event
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        // In test mode or when mock signature passed in testing
        if (app()->environment('testing') && $signature === 'test_signature') {
            $data = json_decode($payload, true);

            return Event::constructFrom($data);
        }

        if (! $webhookSecret) {
            throw new SignatureVerificationException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $webhookSecret);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handlePaymentSucceeded(string $paymentIntentId, ?string $chargeId = null, array $metadata = []): Order
    {
        return DB::transaction(function () use ($paymentIntentId, $chargeId, $metadata): Order {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new \RuntimeException("Payment record with PaymentIntent {$paymentIntentId} not found.");
            }

            /** @var Order $order */
            $order = Order::query()
                ->where('id', $payment->order_id)
                ->with(['items', 'coupon'])
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency check: if payment already processed as succeeded, return immediately
            if ($payment->payment_status === 'succeeded' && $order->order_status === OrderStatus::Paid) {
                return $order;
            }

            // Concurrency-safe inventory deduction with pessimistic locking
            $variantIds = $order->items->pluck('product_variant_id')->filter()->all();
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Revalidate available stock
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                $variant = $variants->get($item->product_variant_id);

                if (! $variant || $variant->stock < $item->quantity) {
                    $payment->update([
                        'payment_status' => 'succeeded',
                        'stripe_charge_id' => $chargeId,
                        'metadata' => array_merge($payment->metadata ?? [], $metadata, [
                            'fulfillment_conflict' => 'Insufficient stock depleted during checkout',
                        ]),
                    ]);

                    $order->update([
                        'payment_status' => PaymentStatus::Paid,
                        'paid_at' => now(),
                    ]);

                    throw new \RuntimeException("Insufficient stock for item {$item->product_name} during payment settlement.");
                }
            }

            // Decrement inventory atomically
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                $variant = $variants->get($item->product_variant_id);
                $variant->decrement('stock', $item->quantity);
            }

            // Update Payment record
            $payment->update([
                'payment_status' => 'succeeded',
                'stripe_charge_id' => $chargeId,
                'metadata' => array_merge($payment->metadata ?? [], $metadata),
            ]);

            // Update Order state
            $order->update([
                'order_status' => OrderStatus::Paid,
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);

            // Track coupon usage count and per-user redemption
            if ($order->coupon_id) {
                Coupon::query()
                    ->where('id', $order->coupon_id)
                    ->increment('usage_count');

                CouponUser::firstOrCreate([
                    'coupon_id' => $order->coupon_id,
                    'user_id' => $order->user_id,
                ], [
                    'order_id' => $order->id,
                    'created_at' => now(),
                ]);
            }

            // Clear customer's active cart items
            Cart::query()
                ->where('user_id', $order->user_id)
                ->first()
                ?->items()
                ->delete();

            // Send confirmation email via Laravel Mail (Mailpit)
            try {
                if ($order->ship_email) {
                    Mail::to($order->ship_email)->send(new OrderConfirmationMail($order));
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to dispatch order confirmation email for {$order->order_number}: {$e->getMessage()}");
            }

            return $order->fresh(['items', 'payment', 'shippingAddress', 'coupon']);
        });
    }

    public function handlePaymentFailed(string $paymentIntentId, ?string $failureReason = null): Order
    {
        return DB::transaction(function () use ($paymentIntentId, $failureReason): Order {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new \RuntimeException("Payment record with PaymentIntent {$paymentIntentId} not found.");
            }

            /** @var Order $order */
            $order = Order::query()
                ->where('id', $payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->update([
                'payment_status' => 'failed',
                'failure_reason' => $failureReason ?? 'Payment declined or failed.',
            ]);

            $order->update([
                'payment_status' => PaymentStatus::Failed,
            ]);

            // Inventory is NOT decremented on failed payment

            return $order->fresh(['items', 'payment']);
        });
    }
}

<?php

namespace App\Services\Customer;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Payment\StripePaymentService;
use App\Services\Settings\CurrencyService;
use App\Support\StripeCurrencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected CurrencyService $currencyService,
        protected StripePaymentService $stripePaymentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(User $user): array
    {
        $cartResult = $this->cartService->getCustomerCart($user);
        /** @var Cart $cart */
        $cart = $cartResult['cart'];
        $warnings = $cartResult['warnings'];

        $financials = $this->calculateFinancials($cart);

        $savedAddresses = $user->shippingAddresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'cart' => $cart,
            'summary' => $financials,
            'addresses' => $savedAddresses,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function processCheckout(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $cart = $this->cartService->getOrCreateCustomerCart($user);
            $this->cartService->loadCartWithRelations($cart);

            $items = $cart->items;

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty. Please add products before checking out.',
                ]);
            }

            // Validate each item for active status and sufficient inventory
            foreach ($items as $item) {
                $variant = $item->productVariant;
                $product = $variant?->product;

                if (! $variant || ! $product || ! $variant->is_active || ! $product->is_active) {
                    $name = $product?->name ?? 'A selected product';
                    throw ValidationException::withMessages([
                        'cart' => "The product '{$name}' is currently unavailable.",
                    ]);
                }

                if ($variant->stock <= 0) {
                    throw ValidationException::withMessages([
                        'cart' => "The product '{$product->name}' is currently out of stock.",
                    ]);
                }

                if ($item->quantity > $variant->stock) {
                    throw ValidationException::withMessages([
                        'cart' => "The requested quantity for '{$product->name}' exceeds available stock ({$variant->stock} in stock).",
                    ]);
                }
            }

            // Calculate financials authoritatively from live DB records
            $couponCode = $data['coupon_code'] ?? null;
            $financials = $this->calculateFinancials($cart, $couponCode, $user);

            // Validate currency against Stripe supported currencies
            $currency = $financials['currency'];
            if (! StripeCurrencies::isSupported($currency)) {
                throw ValidationException::withMessages([
                    'currency' => "The active store currency '{$currency}' is not supported for checkout.",
                ]);
            }

            // Resolve shipping address
            $shippingAddress = $this->resolveShippingAddress($user, $data);

            // Generate unique, sequential order number (ORD-YYYY-000001)
            $orderNumber = $this->generateOrderNumber();

            // Create Order record (status: pending, payment_status: pending)
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'shipping_address_id' => $shippingAddress->id,
                'coupon_id' => $financials['coupon']?->id,
                'order_status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'currency' => $currency,
                'subtotal' => $financials['subtotal'],
                'product_discount' => $financials['product_discount'],
                'coupon_discount' => $financials['coupon_discount'],
                'shipping_charge' => $financials['shipping_charge'],
                'final_total' => $financials['final_total'],
                'ship_name' => $shippingAddress->name,
                'ship_email' => $shippingAddress->email,
                'ship_phone' => $shippingAddress->phone,
                'ship_address' => $shippingAddress->address,
                'ship_city' => $shippingAddress->city,
                'ship_state' => $shippingAddress->state,
                'ship_postal_code' => $shippingAddress->postal_code,
                'ship_country' => $shippingAddress->country,
            ]);

            // Create immutable historical snapshots in order_items
            foreach ($items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->productVariant;
                $product = $variant->product;

                $effectivePrice = (float) (
                    $variant->price_override
                    ?? $product->discount_price
                    ?? $product->regular_price
                    ?? 0.00
                );

                $primaryImage = $product->images?->firstWhere('is_primary', true)
                    ?? $product->images?->first();

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'image_url' => $primaryImage?->image_path,
                    'sku' => $variant->sku,
                    'color_name' => $variant->color?->name ?? 'Default',
                    'size_name' => $variant->size?->name ?? 'One Size',
                    'unit_price' => $effectivePrice,
                    'quantity' => (int) $item->quantity,
                    'line_subtotal' => round($effectivePrice * (int) $item->quantity, 2),
                ]);
            }

            // Clear customer cart items after order creation
            $cart->items()->delete();

            // Create Stripe PaymentIntent and Pending Payment record
            $paymentIntent = $this->stripePaymentService->createPaymentIntent($order);

            Payment::create([
                'order_id' => $order->id,
                'payment_provider' => 'stripe',
                'stripe_payment_intent_id' => $paymentIntent['id'],
                'amount' => $order->final_total,
                'currency' => $order->currency,
                'payment_status' => 'pending',
            ]);

            $order->client_secret = $paymentIntent['client_secret'];

            return $order->load(['items', 'shippingAddress', 'coupon', 'payment']);
        });
    }

    /**
     * @return array{
     *     subtotal: float,
     *     product_discount: float,
     *     net_subtotal: float,
     *     coupon_discount: float,
     *     shipping_charge: float,
     *     final_total: float,
     *     currency: string,
     *     coupon: Coupon|null
     * }
     */
    public function calculateFinancials(Cart $cart, ?string $overrideCouponCode = null, ?User $user = null): array
    {
        $items = $cart->relationLoaded('items') ? $cart->items : $cart->items()->with(['productVariant.product'])->get();

        $grossSubtotal = 0.00;
        $productDiscount = 0.00;

        foreach ($items as $item) {
            $variant = $item->productVariant;
            $product = $variant?->product;

            $quantity = (int) $item->quantity;

            $effectivePrice = (float) (
                $variant?->price_override
                ?? $product?->discount_price
                ?? $product?->regular_price
                ?? 0.00
            );

            $regularPrice = (float) ($product?->regular_price ?? $effectivePrice);

            $grossSubtotal += round($regularPrice * $quantity, 2);

            if ($regularPrice > $effectivePrice) {
                $productDiscount += round(($regularPrice - $effectivePrice) * $quantity, 2);
            }
        }

        $grossSubtotal = round($grossSubtotal, 2);
        $productDiscount = round($productDiscount, 2);
        $netSubtotal = round(max(0.00, $grossSubtotal - $productDiscount), 2);

        // Coupon resolution and validation
        $coupon = null;
        if ($overrideCouponCode) {
            $coupon = Coupon::query()
                ->where('code', strtoupper(trim($overrideCouponCode)))
                ->first();

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'The provided coupon code is invalid.',
                ]);
            }
        } else {
            $coupon = $cart->relationLoaded('coupon') ? $cart->coupon : $cart->coupon;
        }

        $couponDiscount = 0.00;

        if ($coupon) {
            $customer = $user ?? $cart->user;
            $this->validateCouponEligibility($coupon, $netSubtotal, $customer);
            $couponDiscount = round(min($coupon->calculateDiscount($netSubtotal), $netSubtotal), 2);
        }

        // Shipping charge calculation
        $shippingEnabled = filter_var(
            AppSetting::query()
                ->where('setting_key', 'shipping_enabled')
                ->value('setting_value') ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );

        $shippingFee = (float) (
            AppSetting::query()
                ->where('setting_key', 'shipping_fee')
                ->value('setting_value') ?? 5.00
        );

        $shippingCharge = ($shippingEnabled && $items->isNotEmpty()) ? round($shippingFee, 2) : 0.00;

        // Final total: subtotal - product_discount - coupon_discount + shipping_charge
        $finalTotal = max(0.00, round($grossSubtotal - $productDiscount - $couponDiscount + $shippingCharge, 2));

        $currency = $this->currencyService->getStoreCurrency();

        return [
            'subtotal' => $grossSubtotal,
            'product_discount' => $productDiscount,
            'net_subtotal' => $netSubtotal,
            'coupon_discount' => $couponDiscount,
            'shipping_charge' => $shippingCharge,
            'final_total' => $finalTotal,
            'currency' => $currency,
            'coupon' => $coupon,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveShippingAddress(User $user, array $data): ShippingAddress
    {
        if (! empty($data['shipping_address_id'])) {
            $address = $user->shippingAddresses()->find($data['shipping_address_id']);

            if (! $address) {
                throw ValidationException::withMessages([
                    'shipping_address_id' => 'The selected shipping address was not found or does not belong to you.',
                ]);
            }

            return $address;
        }

        // New address payload provided
        $isFirstAddress = $user->shippingAddresses()->count() === 0;

        return $user->shippingAddresses()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'is_default' => $isFirstAddress,
        ]);
    }

    protected function validateCouponEligibility(Coupon $coupon, float $subtotal, ?User $user): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is currently inactive.',
            ]);
        }

        if ($coupon->valid_from && now()->lt($coupon->valid_from)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is not yet valid.',
            ]);
        }

        if ($coupon->valid_until && now()->gt($coupon->valid_until)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has expired.',
            ]);
        }

        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has reached its maximum usage limit.',
            ]);
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            $formattedMin = number_format((float) $coupon->min_order_amount, 2);
            throw ValidationException::withMessages([
                'coupon' => "A minimum order subtotal of {$formattedMin} is required to apply this coupon.",
            ]);
        }

        if ($user) {
            $alreadyRedeemed = CouponUser::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyRedeemed) {
                throw ValidationException::withMessages([
                    'coupon' => 'You have already redeemed this coupon.',
                ]);
            }
        }
    }

    public function generateOrderNumber(): string
    {
        $year = now()->year;
        $prefix = "ORD-{$year}-";

        // Query the latest order with this year's prefix
        $latestOrder = Order::query()
            ->where('order_number', 'LIKE', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $sequence = 1;

        if ($latestOrder) {
            $parts = explode('-', $latestOrder->order_number);
            if (count($parts) === 3 && is_numeric($parts[2])) {
                $sequence = ((int) $parts[2]) + 1;
            }
        }

        return sprintf('ORD-%d-%06d', $year, $sequence);
    }
}

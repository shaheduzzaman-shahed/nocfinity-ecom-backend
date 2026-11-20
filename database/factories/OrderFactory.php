<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = now()->year;

        return [
            'order_number' => sprintf('ORD-%d-%06d', $year, fake()->unique()->numberBetween(1, 999999)),
            'user_id' => User::factory(),
            'shipping_address_id' => ShippingAddress::factory(),
            'coupon_id' => null,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'currency' => 'USD',
            'subtotal' => 100.00,
            'product_discount' => 0.00,
            'coupon_discount' => 0.00,
            'shipping_charge' => 5.00,
            'final_total' => 105.00,
            'ship_name' => fake()->name(),
            'ship_email' => fake()->safeEmail(),
            'ship_phone' => fake()->phoneNumber(),
            'ship_address' => fake()->streetAddress(),
            'ship_city' => fake()->city(),
            'ship_state' => fake()->state(),
            'ship_postal_code' => fake()->postcode(),
            'ship_country' => 'United States',
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional(0.7)->sentence(4),
            'comment' => fake()->paragraph(),
            'status' => 'pending',
            'is_verified_purchase' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_purchase' => true,
        ]);
    }
}

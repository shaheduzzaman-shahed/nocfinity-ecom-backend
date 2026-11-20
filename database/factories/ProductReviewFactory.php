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
    protected $model = ProductReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'order_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'comment' => fake()->paragraph(),
            'status' => 'pending',
            'is_verified_purchase' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['is_verified_purchase' => true]);
    }

    public function rating(int $rating): static
    {
        return $this->state(fn () => ['rating' => $rating]);
    }
}

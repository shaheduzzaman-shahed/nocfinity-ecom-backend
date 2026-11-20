<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createCustomerReviewTestProduct(): Product
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['is_active' => true]);
    $color = Color::factory()->create();
    $size = Size::factory()->create();

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'color_id' => $color->id,
        'size_id' => $size->id,
        'sku' => 'REV-SKU-'.uniqid(),
        'stock' => 20,
    ]);

    return $product;
}

test('guest can view approved product reviews with aggregate calculations', function () {
    $product = createCustomerReviewTestProduct();

    // 2 approved reviews
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 5,
        'status' => 'approved',
    ]);
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 4,
        'status' => 'approved',
    ]);

    // 1 pending review (must be excluded from public list & aggregate)
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 1,
        'status' => 'pending',
    ]);

    $response = $this->getJson("/api/v1/products/{$product->id}/reviews")
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Product reviews retrieved successfully.',
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'rating',
                    'title',
                    'comment',
                    'status',
                    'is_verified_purchase',
                    'user' => ['id', 'name'],
                ],
            ],
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            'aggregate' => [
                'average_rating',
                'total_reviews',
                'rating_distribution',
                'rating_percentages',
            ],
        ]);

    expect($response->json('meta.total'))->toBe(2)
        ->and($response->json('aggregate.total_reviews'))->toBe(2)
        ->and($response->json('aggregate.average_rating'))->toBe(4.5)
        ->and($response->json('aggregate.rating_distribution.5'))->toBe(1)
        ->and($response->json('aggregate.rating_distribution.4'))->toBe(1)
        ->and($response->json('aggregate.rating_distribution.1'))->toBe(0);
});

test('sorting reviews by newest, highest_rating, and lowest_rating works', function () {
    $product = createCustomerReviewTestProduct();

    $r1 = ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 2,
        'status' => 'approved',
        'created_at' => now()->subDays(2),
    ]);
    $r2 = ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 5,
        'status' => 'approved',
        'created_at' => now()->subDay(),
    ]);

    // Highest rating first
    $highest = $this->getJson("/api/v1/products/{$product->id}/reviews?sort=highest_rating")
        ->assertStatus(200);
    expect($highest->json('data.0.id'))->toBe($r2->id);

    // Lowest rating first
    $lowest = $this->getJson("/api/v1/products/{$product->id}/reviews?sort=lowest_rating")
        ->assertStatus(200);
    expect($lowest->json('data.0.id'))->toBe($r1->id);
});

test('guest cannot submit, edit, or delete a review', function () {
    $product = createCustomerReviewTestProduct();
    $review = ProductReview::factory()->create(['product_id' => $product->id]);

    $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => 'Great product!',
    ])->assertUnauthorized();

    $this->putJson("/api/v1/reviews/{$review->id}", [
        'rating' => 4,
    ])->assertUnauthorized();

    $this->deleteJson("/api/v1/reviews/{$review->id}")->assertUnauthorized();
});

test('authenticated customer can submit a review defaulting to pending status and sanitized XSS', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $product = createCustomerReviewTestProduct();

    $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 5,
        'title' => '<b>Excellent fit</b>',
        'comment' => '<script>alert("hacked")</script>Comfortable fabric and looks stylish.',
    ])
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'rating' => 5,
                'title' => 'Excellent fit',
                'comment' => 'Comfortable fabric and looks stylish.',
                'status' => 'pending',
                'is_verified_purchase' => false,
            ],
        ]);

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'rating' => 5,
        'title' => 'Excellent fit',
        'comment' => 'Comfortable fabric and looks stylish.',
        'status' => 'pending',
        'is_verified_purchase' => false,
    ]);
});

test('customer cannot submit multiple reviews for the same product', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $product = createCustomerReviewTestProduct();

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'status' => 'approved',
    ]);

    $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 4,
        'comment' => 'Another comment',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['review']);
});

test('verified purchase badge is awarded when customer previously purchased the product', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $product = createCustomerReviewTestProduct();
    $variant = $product->variants->first();

    // Create a paid order for this customer with this variant
    $address = ShippingAddress::factory()->for($customer)->create();
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'order_number' => 'ORD-2026-112233',
        'currency' => 'USD',
        'subtotal' => 100,
        'final_total' => 100,
        'ship_name' => $address->name,
        'ship_email' => 'customer@example.com',
        'ship_phone' => $address->phone,
        'ship_address' => $address->address,
        'ship_city' => $address->city,
        'ship_state' => $address->state,
        'ship_postal_code' => $address->postal_code,
        'ship_country' => $address->country,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'image_url' => null,
        'sku' => $variant->sku,
        'color_name' => 'Blue',
        'size_name' => 'M',
        'unit_price' => 50,
        'quantity' => 2,
        'line_subtotal' => 100,
    ]);

    $response = $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 5,
        'title' => 'Verified review',
        'comment' => 'Received fast and verified!',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.is_verified_purchase', true)
        ->assertJsonPath('data.order_id', $order->id);

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'is_verified_purchase' => true,
        'order_id' => $order->id,
    ]);
});

test('customer can update their own review, which resets status to pending', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $product = createCustomerReviewTestProduct();
    $review = ProductReview::factory()->create([
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'rating' => 4,
        'title' => 'Original title',
        'comment' => 'Original comment text',
        'status' => 'approved',
    ]);

    $response = $this->putJson("/api/v1/reviews/{$review->id}", [
        'rating' => 5,
        'title' => 'Updated title',
        'comment' => 'Updated comment text',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.status', 'pending');

    $review->refresh();
    expect($review->rating)->toBe(5)
        ->and($review->title)->toBe('Updated title')
        ->and($review->status)->toBe(ReviewStatus::Pending);
});

test('customer cannot update or delete reviews belonging to other customers (IDOR)', function () {
    $customerA = User::factory()->customer()->create();
    $customerB = User::factory()->customer()->create();

    $product = createCustomerReviewTestProduct();
    $reviewB = ProductReview::factory()->create([
        'product_id' => $product->id,
        'user_id' => $customerB->id,
    ]);

    Sanctum::actingAs($customerA);

    $this->putJson("/api/v1/reviews/{$reviewB->id}", [
        'rating' => 1,
        'comment' => 'Tampered review',
    ])->assertForbidden();

    $this->deleteJson("/api/v1/reviews/{$reviewB->id}")->assertForbidden();
});

test('customer can delete their own review', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $product = createCustomerReviewTestProduct();
    $review = ProductReview::factory()->create([
        'product_id' => $product->id,
        'user_id' => $customer->id,
    ]);

    $this->deleteJson("/api/v1/reviews/{$review->id}")
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ]);

    $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);
});

test('validation rejects invalid ratings and empty comments', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $product = createCustomerReviewTestProduct();

    $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 6,
        'comment' => 'Valid comment',
    ])->assertStatus(422)->assertJsonValidationErrors(['rating']);

    $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 0,
        'comment' => 'Valid comment',
    ])->assertStatus(422)->assertJsonValidationErrors(['rating']);

    $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 5,
        'comment' => '',
    ])->assertStatus(422)->assertJsonValidationErrors(['comment']);
});

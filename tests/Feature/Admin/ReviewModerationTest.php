<?php

use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

if (! function_exists('createReviewTestProduct')) {
    function createReviewTestProduct(array $attributes = []): Product
    {
        $category = Category::factory()->create();

        return Product::factory()->for($category)->create(array_merge([
            'is_active' => true,
        ], $attributes));
    }
}

it('allows super admin to list all reviews with pagination', function () {
    $superAdmin = superAdminUser();
    $product = createReviewTestProduct();
    ProductReview::factory()->count(5)->create(['product_id' => $product->id]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/reviews');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Reviews retrieved successfully.',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect(count($response->json('data')))->toBe(5);
});

it('allows staff with reviews.view permission to list reviews', function () {
    $staff = staffWithPermissions(['reviews.view']);
    $product = createReviewTestProduct();
    ProductReview::factory()->count(3)->create(['product_id' => $product->id]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/reviews');
    $response->assertOk();
    expect(count($response->json('data')))->toBe(3);
});

it('denies staff without reviews.view permission from listing reviews', function () {
    $staff = staffWithPermissions(['orders.view']);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/reviews');
    $response->assertForbidden();
});

it('denies customers from accessing admin review endpoints', function () {
    $customer = User::factory()->customer()->create();

    Sanctum::actingAs($customer, ['customer']);

    $response = $this->getJson('/api/v1/admin/reviews');
    $response->assertForbidden();
});

it('denies unauthenticated guests from accessing admin review endpoints', function () {
    $response = $this->getJson('/api/v1/admin/reviews');
    $response->assertUnauthorized();
});

it('filters reviews by status correctly', function () {
    $superAdmin = superAdminUser();
    $product = createReviewTestProduct();

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'status' => ReviewStatus::Pending,
    ]);
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'status' => ReviewStatus::Approved,
    ]);
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'status' => ReviewStatus::Rejected,
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $pendingResponse = $this->getJson('/api/v1/admin/reviews?status=pending');
    $pendingResponse->assertOk();
    expect($pendingResponse->json('meta.total'))->toBe(1)
        ->and($pendingResponse->json('data.0.status'))->toBe('pending');

    $approvedResponse = $this->getJson('/api/v1/admin/reviews?status=approved');
    $approvedResponse->assertOk();
    expect($approvedResponse->json('meta.total'))->toBe(1)
        ->and($approvedResponse->json('data.0.status'))->toBe('approved');
});

it('filters reviews by rating and product_id', function () {
    $superAdmin = superAdminUser();
    $productA = createReviewTestProduct(['name' => 'Product A']);
    $productB = createReviewTestProduct(['name' => 'Product B']);

    ProductReview::factory()->create([
        'product_id' => $productA->id,
        'rating' => 5,
    ]);
    ProductReview::factory()->create([
        'product_id' => $productA->id,
        'rating' => 3,
    ]);
    ProductReview::factory()->create([
        'product_id' => $productB->id,
        'rating' => 5,
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson("/api/v1/admin/reviews?product_id={$productA->id}&rating=5");
    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.rating'))->toBe(5)
        ->and($response->json('data.0.product.id'))->toBe($productA->id);
});

it('searches reviews by text in title or comment', function () {
    $superAdmin = superAdminUser();
    $product = createReviewTestProduct();

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'title' => 'Exceptional tailoring and fabric quality',
        'comment' => 'This is the best item I ever purchased',
    ]);
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'title' => 'Average item',
        'comment' => 'Color did not match expectations',
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/reviews?search=tailoring');
    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.title'))->toContain('tailoring');
});

it('allows staff to view single review details', function () {
    $staff = staffWithPermissions(['reviews.view']);
    $product = createReviewTestProduct();
    $review = ProductReview::factory()->create([
        'product_id' => $product->id,
        'title' => 'Great product',
        'rating' => 5,
    ]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson("/api/v1/admin/reviews/{$review->id}");
    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $review->id,
                'title' => 'Great product',
                'rating' => 5,
            ],
        ]);
});

it('allows staff with reviews.manage to approve a pending review', function () {
    $staff = staffWithPermissions(['reviews.manage']);
    $product = createReviewTestProduct();
    $review = ProductReview::factory()->create([
        'product_id' => $product->id,
        'status' => ReviewStatus::Pending,
    ]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->patchJson("/api/v1/admin/reviews/{$review->id}/status", [
        'status' => 'approved',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $review->id,
                'status' => 'approved',
            ],
        ]);

    expect($review->fresh()->status)->toBe(ReviewStatus::Approved);
});

it('allows staff with reviews.manage to reject a review', function () {
    $staff = staffWithPermissions(['reviews.manage']);
    $product = createReviewTestProduct();
    $review = ProductReview::factory()->create([
        'product_id' => $product->id,
        'status' => ReviewStatus::Pending,
    ]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->patchJson("/api/v1/admin/reviews/{$review->id}/status", [
        'status' => 'rejected',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $review->id,
                'status' => 'rejected',
            ],
        ]);

    expect($review->fresh()->status)->toBe(ReviewStatus::Rejected);
});

it('denies staff without reviews.manage from updating review status', function () {
    $staff = staffWithPermissions(['reviews.view']);
    $product = createReviewTestProduct();
    $review = ProductReview::factory()->create(['product_id' => $product->id]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->patchJson("/api/v1/admin/reviews/{$review->id}/status", [
        'status' => 'approved',
    ]);

    $response->assertForbidden();
});

it('allows staff with reviews.delete to delete a review', function () {
    $staff = staffWithPermissions(['reviews.delete']);
    $product = createReviewTestProduct();
    $review = ProductReview::factory()->create(['product_id' => $product->id]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->deleteJson("/api/v1/admin/reviews/{$review->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ]);

    $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);
});

it('denies staff without reviews.delete from deleting a review', function () {
    $staff = staffWithPermissions(['reviews.manage']);
    $product = createReviewTestProduct();
    $review = ProductReview::factory()->create(['product_id' => $product->id]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->deleteJson("/api/v1/admin/reviews/{$review->id}");

    $response->assertForbidden();
});

it('recalculates product aggregate rating on storefront when review status is updated', function () {
    $superAdmin = superAdminUser();
    $product = createReviewTestProduct();

    // 1 approved review with 4 stars
    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 4,
        'status' => ReviewStatus::Approved,
    ]);

    // 1 pending review with 2 stars
    $pendingReview = ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 2,
        'status' => ReviewStatus::Pending,
    ]);

    // Storefront check before approval: average should be 4.0, count 1
    $storefrontInitial = $this->getJson("/api/v1/products/{$product->id}/reviews");
    $storefrontInitial->assertOk();
    expect($storefrontInitial->json('aggregate.total_reviews'))->toBe(1)
        ->and((float) $storefrontInitial->json('aggregate.average_rating'))->toBe(4.0);

    // Admin approves the 2-star review
    Sanctum::actingAs($superAdmin, ['admin']);
    $this->patchJson("/api/v1/admin/reviews/{$pendingReview->id}/status", [
        'status' => 'approved',
    ])->assertOk();

    // Storefront check after approval: average should be (4+2)/2 = 3.0, count 2
    $storefrontUpdated = $this->getJson("/api/v1/products/{$product->id}/reviews");
    $storefrontUpdated->assertOk();
    expect($storefrontUpdated->json('aggregate.total_reviews'))->toBe(2)
        ->and((float) $storefrontUpdated->json('aggregate.average_rating'))->toBe(3.0);
});

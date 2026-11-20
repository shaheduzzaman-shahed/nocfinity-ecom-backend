<?php

namespace App\Services\Customer;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CustomerReviewService
{
    /**
     * List approved product reviews with aggregates and optional user review.
     *
     * @param  array<string, mixed>  $params
     * @return array{
     *     paginator: LengthAwarePaginator,
     *     aggregate: array<string, mixed>,
     *     user_review: ProductReview|null
     * }
     */
    public function listProductReviews(Product $product, array $params = [], ?User $currentUser = null): array
    {
        $query = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ReviewStatus::Approved)
            ->with('user:id,name');

        $sort = $params['sort'] ?? 'newest';
        match ($sort) {
            'highest_rating' => $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc'),
            'lowest_rating' => $query->orderBy('rating', 'asc')->orderBy('created_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min(max((int) ($params['per_page'] ?? 10), 1), 50);
        $paginator = $query->paginate($perPage);

        // Compute overall aggregates on all approved reviews for this product
        $approvedReviews = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ReviewStatus::Approved);

        $totalReviews = (int) $approvedReviews->count();
        $averageRating = $totalReviews > 0 ? round((float) $approvedReviews->avg('rating'), 1) : 0.0;

        $counts = (clone $approvedReviews)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $ratingDistribution = [
            5 => (int) ($counts[5] ?? 0),
            4 => (int) ($counts[4] ?? 0),
            3 => (int) ($counts[3] ?? 0),
            2 => (int) ($counts[2] ?? 0),
            1 => (int) ($counts[1] ?? 0),
        ];

        $ratingPercentages = [];
        foreach ($ratingDistribution as $star => $count) {
            $ratingPercentages[$star] = $totalReviews > 0
                ? round(($count / $totalReviews) * 100, 1)
                : 0.0;
        }

        // Check if the current user already has a review for this product
        $userReview = null;
        if ($currentUser) {
            $userReview = ProductReview::query()
                ->where('product_id', $product->id)
                ->where('user_id', $currentUser->id)
                ->with('user:id,name')
                ->first();
        }

        return [
            'paginator' => $paginator,
            'aggregate' => [
                'average_rating' => $averageRating,
                'total_reviews' => $totalReviews,
                'rating_distribution' => $ratingDistribution,
                'rating_percentages' => $ratingPercentages,
            ],
            'user_review' => $userReview,
        ];
    }

    private function sanitizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Strip full script and style tags along with their inner content
        $cleaned = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $text);
        $stripped = strip_tags(trim((string) $cleaned));

        return $stripped !== '' ? $stripped : null;
    }

    /**
     * Submit a customer review enforcing unique constraint, verified purchase check, and XSS sanitization.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function storeReview(Product $product, User $user, array $data): ProductReview
    {
        // Enforce one review per product per user
        $exists = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'review' => 'You have already submitted a review for this product.',
            ]);
        }

        // Verified purchase check
        $order = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('order_status', [
                OrderStatus::Paid,
                OrderStatus::Processing,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
            ])
            ->whereHas('items.productVariant', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->latest()
            ->first();

        $isVerifiedPurchase = $order !== null;
        $orderId = $order?->id;

        // XSS sanitization
        $title = $this->sanitizeText($data['title'] ?? null);
        $comment = (string) $this->sanitizeText($data['comment'] ?? '');

        return ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $orderId,
            'rating' => (int) $data['rating'],
            'title' => $title,
            'comment' => $comment,
            'status' => ReviewStatus::Pending,
            'is_verified_purchase' => $isVerifiedPurchase,
        ]);
    }

    /**
     * Update an existing customer review enforcing ownership and re-triggering moderation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReview(ProductReview $review, User $user, array $data): ProductReview
    {
        if ($review->user_id !== $user->id) {
            abort(403, 'You are not authorized to modify this review.');
        }

        if (isset($data['rating'])) {
            $review->rating = (int) $data['rating'];
        }

        if (array_key_exists('title', $data)) {
            $review->title = $this->sanitizeText($data['title']);
        }

        if (isset($data['comment'])) {
            $review->comment = (string) $this->sanitizeText($data['comment']);
        }

        // Edits reset approval back to pending
        $review->status = ReviewStatus::Pending;
        $review->save();

        return $review->fresh('user');
    }

    /**
     * Delete an existing customer review enforcing ownership.
     */
    public function deleteReview(ProductReview $review, User $user): void
    {
        if ($review->user_id !== $user->id) {
            abort(403, 'You are not authorized to delete this review.');
        }

        $review->delete();
    }
}

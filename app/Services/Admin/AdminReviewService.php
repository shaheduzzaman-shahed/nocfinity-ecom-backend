<?php

namespace App\Services\Admin;

use App\Enums\ReviewStatus;
use App\Models\ProductReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminReviewService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateReviews(array $filters): LengthAwarePaginator
    {
        $query = ProductReview::query()
            ->with(['product.primaryImage', 'user', 'order']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['rating'])) {
            $query->where('rating', (int) $filters['rating']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['created_at', 'rating', 'status'], true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    public function getReview(int $id): ProductReview
    {
        return ProductReview::with(['product.primaryImage', 'user', 'order'])->findOrFail($id);
    }

    public function updateStatus(ProductReview $review, ReviewStatus $status): ProductReview
    {
        $review->update(['status' => $status]);

        return $review->fresh(['product.primaryImage', 'user', 'order']);
    }

    public function deleteReview(ProductReview $review): void
    {
        $review->delete();
    }
}

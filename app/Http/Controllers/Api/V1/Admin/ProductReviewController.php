<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Review\AdminReviewIndexRequest;
use App\Http\Requests\Api\V1\Admin\Review\UpdateReviewStatusRequest;
use App\Http\Resources\Api\V1\AdminReviewResource;
use App\Models\ProductReview;
use App\Services\Admin\AdminReviewService;
use Illuminate\Http\JsonResponse;

class ProductReviewController extends Controller
{
    public function __construct(
        protected AdminReviewService $reviewService,
    ) {}

    /**
     * Display a paginated listing of customer product reviews.
     */
    public function index(AdminReviewIndexRequest $request): JsonResponse
    {
        $reviews = $this->reviewService
            ->paginateReviews($request->validated())
            ->through(fn (ProductReview $review) => new AdminReviewResource($review));

        return $this->paginatedResponse($reviews, 'Reviews retrieved successfully.');
    }

    /**
     * Display the specified review details.
     */
    public function show(int $id): JsonResponse
    {
        $review = $this->reviewService->getReview($id);

        return $this->successResponse(
            new AdminReviewResource($review),
            'Review details retrieved successfully.'
        );
    }

    /**
     * Update review status (approve or reject).
     */
    public function updateStatus(UpdateReviewStatusRequest $request, int $id): JsonResponse
    {
        $review = $this->reviewService->getReview($id);
        $status = ReviewStatus::from($request->validated('status'));

        $updatedReview = $this->reviewService->updateStatus($review, $status);

        return $this->successResponse(
            new AdminReviewResource($updatedReview),
            "Review status updated to {$status->value} successfully."
        );
    }

    /**
     * Delete an inappropriate review.
     */
    public function destroy(int $id): JsonResponse
    {
        $review = $this->reviewService->getReview($id);
        $this->reviewService->deleteReview($review);

        return $this->successResponse(
            null,
            'Review deleted successfully.'
        );
    }
}

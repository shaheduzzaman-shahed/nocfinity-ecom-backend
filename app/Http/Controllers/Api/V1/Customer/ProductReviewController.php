<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreReviewRequest;
use App\Http\Requests\Api\V1\Customer\UpdateReviewRequest;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Customer\CustomerReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(
        protected CustomerReviewService $reviewService,
    ) {}

    /**
     * Display approved reviews for a product with rating aggregates.
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $currentUser = auth('sanctum')->user();
        $result = $this->reviewService->listProductReviews(
            $product,
            $request->query(),
            $currentUser
        );

        $paginator = $result['paginator'];
        $items = ProductReviewResource::collection($paginator->items());

        return response()->json([
            'success' => true,
            'message' => 'Product reviews retrieved successfully.',
            'data' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'aggregate' => $result['aggregate'],
            'user_review' => $result['user_review']
                ? new ProductReviewResource($result['user_review'])
                : null,
        ]);
    }

    /**
     * Submit a new customer review for a product.
     */
    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        $review = $this->reviewService->storeReview(
            $product,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully and is awaiting moderation.',
            'data' => new ProductReviewResource($review),
        ], 201);
    }

    /**
     * Update customer's own review.
     */
    public function update(UpdateReviewRequest $request, ProductReview $review): JsonResponse
    {
        $updated = $this->reviewService->updateReview(
            $review,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully and is awaiting moderation.',
            'data' => new ProductReviewResource($updated),
        ]);
    }

    /**
     * Delete customer's own review.
     */
    public function destroy(Request $request, ProductReview $review): JsonResponse
    {
        $this->reviewService->deleteReview($review, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ]);
    }
}

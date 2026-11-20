<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductCardResource;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;

class FeaturedProductController extends Controller
{
    public function index(ProductService $productService): JsonResponse
    {
        return $this->successResponse(
            ProductCardResource::collection($productService->featured()),
            'Featured products retrieved successfully.',
        );
    }
}

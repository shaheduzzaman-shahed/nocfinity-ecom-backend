<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ProductIndexRequest;
use App\Http\Resources\Api\V1\ProductCardResource;
use App\Http\Resources\Api\V1\PublicProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(
        ProductIndexRequest $request,
        ProductService $productService,
    ): JsonResponse {
        $products = $productService
            ->publicPaginate($request->validated())
            ->through(fn (Product $product) => new ProductCardResource($product));

        return $this->paginatedResponse($products, 'Products retrieved successfully.');
    }

    public function show(Product $product, ProductService $productService): JsonResponse
    {
        return $this->successResponse(
            new PublicProductResource($productService->findPublic($product)),
            'Product retrieved successfully.',
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Product\AdminProductIndexRequest;
use App\Http\Requests\Api\V1\Admin\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\Product\UpdateProductRequest;
use App\Http\Resources\Api\V1\AdminProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(
        AdminProductIndexRequest $request,
        ProductService $productService,
    ): JsonResponse {
        $products = $productService
            ->adminPaginate($request->validated())
            ->through(fn (Product $product) => new AdminProductResource($product));

        return $this->paginatedResponse($products, 'Products retrieved successfully.');
    }

    public function store(
        StoreProductRequest $request,
        ProductService $productService,
    ): JsonResponse {
        return $this->successResponse(
            new AdminProductResource(
                $productService->create($request->validated()),
            ),
            'Product created successfully.',
            201,
        );
    }

    public function show(Product $product, ProductService $productService): JsonResponse
    {
        return $this->successResponse(
            new AdminProductResource($productService->find($product)),
            'Product retrieved successfully.',
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        ProductService $productService,
    ): JsonResponse {
        return $this->successResponse(
            new AdminProductResource(
                $productService->update($product, $request->validated()),
            ),
            'Product updated successfully.',
        );
    }

    public function destroy(Product $product, ProductService $productService): JsonResponse
    {
        $productService->delete($product);

        return $this->successResponse(null, 'Product deleted successfully.');
    }
}

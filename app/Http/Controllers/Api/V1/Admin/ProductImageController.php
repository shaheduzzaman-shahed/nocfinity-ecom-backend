<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Product\StoreProductImageRequest;
use App\Http\Requests\Api\V1\Admin\Product\UpdateProductImageRequest;
use App\Http\Resources\Api\V1\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductImageService;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function store(
        StoreProductImageRequest $request,
        Product $product,
        ProductImageService $imageService,
    ): JsonResponse {
        $images = $request->file('images', []);
        $files = is_array($images) ? $images : [$images];

        $singleImage = $request->file('image');

        if ($singleImage) {
            $files[] = $singleImage;
        }

        $uploaded = $imageService->addImages(
            $product,
            array_values(array_filter($files)),
            $request->validated(),
        );

        return $this->successResponse(
            ProductImageResource::collection($uploaded),
            'Product images uploaded successfully.',
            201,
        );
    }

    public function update(
        UpdateProductImageRequest $request,
        Product $product,
        ProductImage $image,
        ProductImageService $imageService,
    ): JsonResponse {
        return $this->successResponse(
            new ProductImageResource(
                $imageService->updateImage($product, $image, $request->validated()),
            ),
            'Product image updated successfully.',
        );
    }

    public function destroy(
        Product $product,
        ProductImage $image,
        ProductImageService $imageService,
    ): JsonResponse {
        $imageService->deleteImage($product, $image);

        return $this->successResponse(null, 'Product image deleted successfully.');
    }
}

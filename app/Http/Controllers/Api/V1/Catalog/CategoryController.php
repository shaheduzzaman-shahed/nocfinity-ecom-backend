<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(CategoryService $categoryService): JsonResponse
    {
        return $this->successResponse(
            CategoryResource::collection($categoryService->publicTree()),
            'Categories retrieved successfully.',
        );
    }

    public function show(Category $category, CategoryService $categoryService): JsonResponse
    {
        return $this->successResponse(
            new CategoryResource($categoryService->findPublic($category)),
            'Category retrieved successfully.',
        );
    }
}

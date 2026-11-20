<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\Category\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(CategoryService $categoryService): JsonResponse
    {
        return $this->successResponse(
            CategoryResource::collection($categoryService->adminTree()),
            'Categories retrieved successfully.',
        );
    }

    public function store(StoreCategoryRequest $request, CategoryService $categoryService): JsonResponse
    {
        return $this->successResponse(
            new CategoryResource($categoryService->create($request->validated())),
            'Category created successfully.',
            201,
        );
    }

    public function show(Category $category, CategoryService $categoryService): JsonResponse
    {
        return $this->successResponse(
            new CategoryResource($categoryService->find($category)),
            'Category retrieved successfully.',
        );
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        CategoryService $categoryService,
    ): JsonResponse {
        return $this->successResponse(
            new CategoryResource($categoryService->update($category, $request->validated())),
            'Category updated successfully.',
        );
    }

    public function destroy(Category $category, CategoryService $categoryService): JsonResponse
    {
        $categoryService->delete($category);

        return $this->successResponse(null, 'Category deleted successfully.');
    }
}

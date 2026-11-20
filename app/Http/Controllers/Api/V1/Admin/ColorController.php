<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Color\StoreColorRequest;
use App\Http\Requests\Api\V1\Admin\Color\UpdateColorRequest;
use App\Http\Resources\Api\V1\ColorResource;
use App\Models\Color;
use App\Services\Catalog\ColorService;
use Illuminate\Http\JsonResponse;

class ColorController extends Controller
{
    public function index(ColorService $colorService): JsonResponse
    {
        return $this->successResponse(
            ColorResource::collection($colorService->list()),
            'Colors retrieved successfully.',
        );
    }

    public function store(StoreColorRequest $request, ColorService $colorService): JsonResponse
    {
        return $this->successResponse(
            new ColorResource($colorService->create($request->validated())),
            'Color created successfully.',
            201,
        );
    }

    public function update(
        UpdateColorRequest $request,
        Color $color,
        ColorService $colorService,
    ): JsonResponse {
        return $this->successResponse(
            new ColorResource($colorService->update($color, $request->validated())),
            'Color updated successfully.',
        );
    }

    public function destroy(Color $color, ColorService $colorService): JsonResponse
    {
        $colorService->delete($color);

        return $this->successResponse(null, 'Color deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ColorResource;
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
}

<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SizeResource;
use App\Services\Catalog\SizeService;
use Illuminate\Http\JsonResponse;

class SizeController extends Controller
{
    public function index(SizeService $sizeService): JsonResponse
    {
        return $this->successResponse(
            SizeResource::collection($sizeService->list()),
            'Sizes retrieved successfully.',
        );
    }
}

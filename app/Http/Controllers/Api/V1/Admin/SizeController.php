<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Size\StoreSizeRequest;
use App\Http\Requests\Api\V1\Admin\Size\UpdateSizeRequest;
use App\Http\Resources\Api\V1\SizeResource;
use App\Models\Size;
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

    public function store(StoreSizeRequest $request, SizeService $sizeService): JsonResponse
    {
        return $this->successResponse(
            new SizeResource($sizeService->create($request->validated())),
            'Size created successfully.',
            201,
        );
    }

    public function update(
        UpdateSizeRequest $request,
        Size $size,
        SizeService $sizeService,
    ): JsonResponse {
        return $this->successResponse(
            new SizeResource($sizeService->update($size, $request->validated())),
            'Size updated successfully.',
        );
    }

    public function destroy(Size $size, SizeService $sizeService): JsonResponse
    {
        $sizeService->delete($size);

        return $this->successResponse(null, 'Size deleted successfully.');
    }
}

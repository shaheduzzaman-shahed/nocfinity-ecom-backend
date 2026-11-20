<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Coupon\StoreCouponRequest;
use App\Http\Requests\Api\V1\Admin\Coupon\UpdateCouponRequest;
use App\Http\Resources\Api\V1\CouponResource;
use App\Models\Coupon;
use App\Services\Promotions\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request, CouponService $couponService): JsonResponse
    {
        $coupons = $couponService
            ->list($request->all())
            ->through(fn (Coupon $coupon) => new CouponResource($coupon));

        return $this->paginatedResponse($coupons, 'Coupons retrieved successfully.');
    }

    public function store(StoreCouponRequest $request, CouponService $couponService): JsonResponse
    {
        $coupon = $couponService->create($request->validated());

        return $this->successResponse(
            new CouponResource($coupon),
            'Coupon created successfully.',
            201,
        );
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->successResponse(
            new CouponResource($coupon),
            'Coupon retrieved successfully.',
        );
    }

    public function update(
        UpdateCouponRequest $request,
        Coupon $coupon,
        CouponService $couponService,
    ): JsonResponse {
        $updated = $couponService->update($coupon, $request->validated());

        return $this->successResponse(
            new CouponResource($updated),
            'Coupon updated successfully.',
        );
    }

    public function destroy(Coupon $coupon, CouponService $couponService): JsonResponse
    {
        $couponService->delete($coupon);

        return $this->successResponse(null, 'Coupon deleted successfully.');
    }
}

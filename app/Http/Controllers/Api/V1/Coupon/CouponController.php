<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Coupon\ApplyCouponRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\Promotions\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function apply(ApplyCouponRequest $request, CouponService $couponService): JsonResponse
    {
        $result = $couponService->applyToCart(
            $request->user(),
            (string) $request->validated('code'),
        );

        return $this->successResponse(
            new CartResource($result['cart']),
            'Coupon applied successfully.',
        );
    }

    public function remove(Request $request, CouponService $couponService): JsonResponse
    {
        $cart = $couponService->removeFromCart($request->user());

        return $this->successResponse(
            new CartResource($cart),
            'Coupon removed successfully.',
        );
    }
}

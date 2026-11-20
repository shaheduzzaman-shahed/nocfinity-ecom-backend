<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CheckoutRequest;
use App\Http\Resources\Api\V1\CartItemResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\ShippingAddressResource;
use App\Services\Customer\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $previewData = $this->checkoutService->preview($request->user());

        $cart = $previewData['cart'];
        $summary = $previewData['summary'];
        $addresses = $previewData['addresses'];
        $warnings = $previewData['warnings'];

        return response()->json([
            'success' => true,
            'message' => 'Checkout preview generated successfully.',
            'data' => [
                'items' => CartItemResource::collection($cart->items),
                'items_count' => $cart->items->count(),
                'total_quantity' => $cart->items->sum('quantity'),
                'summary' => [
                    'subtotal' => $summary['subtotal'],
                    'product_discount' => $summary['product_discount'],
                    'net_subtotal' => $summary['net_subtotal'],
                    'coupon_discount' => $summary['coupon_discount'],
                    'shipping_charge' => $summary['shipping_charge'],
                    'final_total' => $summary['final_total'],
                    'currency' => $summary['currency'],
                ],
                'coupon' => $summary['coupon'] ? [
                    'id' => $summary['coupon']->id,
                    'code' => $summary['coupon']->code,
                    'type' => $summary['coupon']->type,
                    'value' => (float) $summary['coupon']->value,
                    'discount_amount' => $summary['coupon_discount'],
                ] : null,
                'addresses' => ShippingAddressResource::collection($addresses),
                'default_address_id' => $addresses->firstWhere('is_default', true)?->id,
                'warnings' => $warnings,
            ],
        ]);
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkoutService->processCheckout($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => new OrderResource($order),
        ], 201);
    }
}

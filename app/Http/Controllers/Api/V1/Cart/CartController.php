<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cart\AddToCartRequest;
use App\Http\Requests\Api\V1\Cart\MergeCartRequest;
use App\Http\Requests\Api\V1\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, CartService $cartService): JsonResponse
    {
        $result = $cartService->getCustomerCart($request->user());

        return $this->successResponse(
            (new CartResource($result['cart']))->withWarnings($result['warnings']),
            'Cart retrieved successfully.',
        );
    }

    public function store(AddToCartRequest $request, CartService $cartService): JsonResponse
    {
        $result = $cartService->addItem(
            $request->user(),
            (int) $request->validated('product_variant_id'),
            (int) $request->validated('quantity'),
        );

        return $this->successResponse(
            (new CartResource($result['cart']))->withWarnings($result['warnings']),
            'Item added to cart successfully.',
            201,
        );
    }

    public function update(
        UpdateCartItemRequest $request,
        int $id,
        CartService $cartService,
    ): JsonResponse {
        $cart = $cartService->updateItem(
            $request->user(),
            $id,
            (int) $request->validated('quantity'),
        );

        return $this->successResponse(
            new CartResource($cart),
            'Cart item updated successfully.',
        );
    }

    public function destroy(
        Request $request,
        int $id,
        CartService $cartService,
    ): JsonResponse {
        $cart = $cartService->removeItem($request->user(), $id);

        return $this->successResponse(
            new CartResource($cart),
            'Cart item removed successfully.',
        );
    }

    public function clear(Request $request, CartService $cartService): JsonResponse
    {
        $cart = $cartService->clearCart($request->user());

        return $this->successResponse(
            new CartResource($cart),
            'Cart cleared successfully.',
        );
    }

    public function merge(MergeCartRequest $request, CartService $cartService): JsonResponse
    {
        $result = $cartService->mergeGuestCart(
            $request->user(),
            $request->validated('items'),
        );

        return $this->successResponse(
            (new CartResource($result['cart']))->withWarnings($result['warnings']),
            'Cart merged successfully.',
        );
    }
}

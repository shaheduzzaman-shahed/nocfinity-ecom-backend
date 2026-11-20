<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function confirmation(Request $request, string|int $id): JsonResponse
    {
        $user = $request->user();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id)->orWhere('order_number', $id);
                } else {
                    $q->where('order_number', $id);
                }
            })
            ->with(['items', 'coupon', 'shippingAddress'])
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order confirmation retrieved successfully.',
            'data' => new OrderResource($order),
        ]);
    }
}

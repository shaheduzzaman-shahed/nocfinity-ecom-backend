<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Services\Customer\CustomerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected CustomerOrderService $orderService,
    ) {}

    /**
     * Display a paginated listing of customer orders.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $orders = $this->orderService
            ->listOrders($request->user(), $perPage)
            ->through(fn (Order $order) => new OrderResource($order));

        return $this->paginatedResponse($orders, 'Customer orders retrieved successfully.');
    }

    /**
     * Display detailed order information.
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($request->user(), $id);

        return $this->successResponse(
            new OrderResource($order),
            'Order details retrieved successfully.'
        );
    }

    /**
     * Order confirmation endpoint for post-checkout redirection.
     */
    public function confirmation(Request $request, string|int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($request->user(), $id);

        return $this->successResponse(
            new OrderResource($order),
            'Order confirmation retrieved successfully.'
        );
    }

    /**
     * Cancel customer order.
     */
    public function cancel(Request $request, string|int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order = $this->orderService->cancelOrder(
            $request->user(),
            $id,
            $request->input('reason')
        );

        return $this->successResponse(
            new OrderResource($order),
            'Order cancelled successfully.'
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Order\AdminOrderIndexRequest;
use App\Http\Requests\Api\V1\Admin\Order\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\AdminOrderResource;
use App\Models\Order;
use App\Services\Admin\AdminOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected AdminOrderService $orderService,
    ) {}

    /**
     * Display a listing of orders with filters and pagination.
     */
    public function index(AdminOrderIndexRequest $request): JsonResponse
    {
        $orders = $this->orderService
            ->paginateOrders($request->validated())
            ->through(fn (Order $order) => new AdminOrderResource($order));

        return $this->paginatedResponse($orders, 'Orders retrieved successfully.');
    }

    /**
     * Display the specified order details.
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($id);

        return $this->successResponse(
            new AdminOrderResource($order),
            'Order details retrieved successfully.'
        );
    }

    /**
     * Update the specified order's status following the lifecycle state machine.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, string|int $id): JsonResponse
    {
        $order = $this->orderService->updateStatus(
            $id,
            $request->validated('status'),
            $request->validated('reason'),
            $request->validated('note')
        );

        return $this->successResponse(
            new AdminOrderResource($order),
            'Order status updated successfully.'
        );
    }
}

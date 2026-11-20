<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminOrderResource;
use App\Models\Order;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    /**
     * Display current dashboard summary stats.
     */
    public function index(): JsonResponse
    {
        $stats = $this->dashboardService->getStats();

        return $this->successResponse(
            $stats,
            'Dashboard statistics retrieved successfully.'
        );
    }

    /**
     * Display trailing daily sales and order volume points.
     */
    public function sales(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $chartData = $this->dashboardService->getSalesChart($days);

        return $this->successResponse(
            $chartData,
            'Sales chart data retrieved successfully.'
        );
    }

    /**
     * Display recent orders.
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 6);
        $orders = $this->dashboardService
            ->getRecentOrders($limit)
            ->map(fn (Order $order) => new AdminOrderResource($order));

        return $this->successResponse(
            $orders,
            'Recent orders retrieved successfully.'
        );
    }
}

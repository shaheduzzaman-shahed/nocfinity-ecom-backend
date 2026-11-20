<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminActivityLogResource;
use App\Models\ActivityLog;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(
        protected AdminActivityLogService $logService,
    ) {}

    /**
     * Paginated list of activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $logs = $this->logService
            ->paginateLogs($request->all())
            ->through(fn (ActivityLog $log) => new AdminActivityLogResource($log));

        return $this->paginatedResponse($logs, 'Activity logs retrieved successfully.');
    }

    /**
     * Single activity log detail.
     */
    public function show(int $id): JsonResponse
    {
        $log = $this->logService->getLog($id);

        return $this->successResponse(
            new AdminActivityLogResource($log),
            'Activity log retrieved successfully.'
        );
    }

    /**
     * Distinct actions for filtering.
     */
    public function actions(): JsonResponse
    {
        $actions = $this->logService->distinctActions();

        return $this->successResponse($actions, 'Action types retrieved successfully.');
    }
}

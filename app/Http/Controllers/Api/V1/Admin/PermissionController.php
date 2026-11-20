<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(RoleService $roleService): JsonResponse
    {
        return $this->successResponse(
            PermissionResource::collection($roleService->permissions()),
            'Permissions retrieved successfully.',
        );
    }
}

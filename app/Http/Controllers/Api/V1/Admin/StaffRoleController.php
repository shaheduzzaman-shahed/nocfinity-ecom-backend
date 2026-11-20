<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Staff\AssignStaffRolesRequest;
use App\Http\Resources\Api\V1\StaffResource;
use App\Models\User;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;

class StaffRoleController extends Controller
{
    public function index(RoleService $roleService): JsonResponse
    {
        return $this->successResponse(
            StaffResource::collection($roleService->staff()),
            'Staff retrieved successfully.',
        );
    }

    public function updateRoles(
        AssignStaffRolesRequest $request,
        User $staff,
        RoleService $roleService,
    ): JsonResponse {
        $staff = $roleService->assignRolesToStaff(
            $request->user(),
            $staff,
            $request->validated('roles'),
        );

        return $this->successResponse(
            new StaffResource($staff),
            'Staff roles updated successfully.',
        );
    }
}

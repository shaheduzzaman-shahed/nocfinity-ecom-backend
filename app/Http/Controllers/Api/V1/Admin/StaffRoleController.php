<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Staff\AssignStaffRolesRequest;
use App\Http\Requests\Api\V1\Admin\Staff\CreateStaffRequest;
use App\Http\Requests\Api\V1\Admin\Staff\ToggleStaffStatusRequest;
use App\Http\Requests\Api\V1\Admin\Staff\UpdateStaffRequest;
use App\Http\Resources\Api\V1\StaffResource;
use App\Models\User;
use App\Services\Admin\RoleService;
use App\Services\Admin\StaffManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffRoleController extends Controller
{
    public function index(StaffManagementService $staffService): JsonResponse
    {
        return $this->successResponse(
            StaffResource::collection($staffService->listStaff()),
            'Staff retrieved successfully.',
        );
    }

    public function store(
        CreateStaffRequest $request,
        StaffManagementService $staffService,
    ): JsonResponse {
        $staff = $staffService->createStaff(
            $request->user(),
            $request->validated(),
        );

        return $this->successResponse(
            new StaffResource($staff),
            'Staff member created successfully.',
            201,
        );
    }

    public function show(
        User $staff,
        StaffManagementService $staffService,
    ): JsonResponse {
        $staffMember = $staffService->getStaff($staff->id);

        return $this->successResponse(
            new StaffResource($staffMember),
            'Staff member retrieved successfully.',
        );
    }

    public function update(
        UpdateStaffRequest $request,
        User $staff,
        StaffManagementService $staffService,
    ): JsonResponse {
        $updatedStaff = $staffService->updateStaff(
            $request->user(),
            $staff,
            $request->validated(),
        );

        return $this->successResponse(
            new StaffResource($updatedStaff),
            'Staff member updated successfully.',
        );
    }

    public function toggleStatus(
        ToggleStaffStatusRequest $request,
        User $staff,
        StaffManagementService $staffService,
    ): JsonResponse {
        $updatedStaff = $staffService->toggleStatus(
            $request->user(),
            $staff,
            $request->validated('is_active'),
        );

        return $this->successResponse(
            new StaffResource($updatedStaff),
            'Staff status updated successfully.',
        );
    }

    public function destroy(
        Request $request,
        User $staff,
        StaffManagementService $staffService,
    ): JsonResponse {
        $staffService->deleteStaff($request->user(), $staff);

        return $this->successResponse(
            null,
            'Staff member deleted successfully.',
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

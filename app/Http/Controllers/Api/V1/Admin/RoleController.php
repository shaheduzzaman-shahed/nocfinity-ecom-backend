<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Api\V1\Admin\Role\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Role;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(RoleService $roleService): JsonResponse
    {
        return $this->successResponse(
            RoleResource::collection($roleService->roles()),
            'Roles retrieved successfully.',
        );
    }

    public function store(StoreRoleRequest $request, RoleService $roleService): JsonResponse
    {
        return $this->successResponse(
            new RoleResource($roleService->create($request->validated())),
            'Role created successfully.',
            201,
        );
    }

    public function show(Role $role, RoleService $roleService): JsonResponse
    {
        return $this->successResponse(
            new RoleResource($roleService->find($role)),
            'Role retrieved successfully.',
        );
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
        RoleService $roleService,
    ): JsonResponse {
        return $this->successResponse(
            new RoleResource($roleService->update($role, $request->validated())),
            'Role updated successfully.',
        );
    }

    public function destroy(Role $role, RoleService $roleService): JsonResponse
    {
        $roleService->delete($role);

        return $this->successResponse(null, 'Role deleted successfully.');
    }
}

<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }

    public function find(Role $role): Role
    {
        return $role->load('permissions')->loadCount('users');
    }

    /**
     * @param  array{name?: string, display_name: string, description?: string|null, permissions?: array<int, string>}  $data
     */
    public function create(array $data): Role
    {
        $role = DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'] ?? $this->generateName($data['display_name']),
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });

        return $role->load('permissions')->loadCount('users');
    }

    /**
     * @param  array{name?: string, display_name?: string, description?: string|null, permissions?: array<int, string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        $this->ensureRoleIsMutable($role);

        DB::transaction(function () use ($role, $data): void {
            $role->fill(Arr::only($data, ['name', 'display_name', 'description']))->save();

            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions($data['permissions']);
            }
        });

        return $role->fresh()->load('permissions')->loadCount('users');
    }

    public function delete(Role $role): void
    {
        $this->ensureRoleIsMutable($role, 'deleted');

        $role->loadCount('users');

        if ($role->users_count > 0) {
            throw new ConflictHttpException(
                'This role is assigned to staff members and cannot be deleted.',
            );
        }

        $role->delete();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function staff(): Collection
    {
        return User::query()
            ->where('account_type', AccountType::Staff)
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, string>  $roleNames
     */
    public function assignRolesToStaff(User $actor, User $staff, array $roleNames): User
    {
        if (! $staff->isStaff()) {
            throw new NotFoundHttpException('Staff member not found.');
        }

        if ($staff->hasRole('super-admin')) {
            throw new AccessDeniedHttpException('The Super Admin account cannot be modified.');
        }

        if (in_array('super-admin', $roleNames, true)) {
            throw new AccessDeniedHttpException(
                'The Super Admin role cannot be assigned through role management.',
            );
        }

        if ($actor->is($staff)) {
            throw new AccessDeniedHttpException('You cannot change your own role assignments.');
        }

        $staff->syncRoles($roleNames);

        return $staff->fresh()->load('roles');
    }

    private function ensureRoleIsMutable(Role $role, string $action = 'modified'): void
    {
        if ($role->is_system) {
            throw new AccessDeniedHttpException(
                "The Super Admin role cannot be {$action}.",
            );
        }
    }

    private function generateName(string $displayName): string
    {
        $base = Str::slug($displayName) ?: 'role';
        $name = $base;
        $suffix = 2;

        while (Role::where('name', $name)->exists()) {
            $name = "{$base}-{$suffix}";
            $suffix++;
        }

        return $name;
    }
}

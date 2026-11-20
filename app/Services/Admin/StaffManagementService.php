<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaffManagementService
{
    /**
     * @return Collection<int, User>
     */
    public function listStaff(): Collection
    {
        return User::query()
            ->where('account_type', AccountType::Staff)
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    public function getStaff(int|string $id): User
    {
        $staff = User::query()
            ->where('account_type', AccountType::Staff)
            ->with(['roles.permissions'])
            ->findOrFail($id);

        return $staff;
    }

    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null, is_active?: bool, roles?: array<int, string>}  $data
     */
    public function createStaff(User $actor, array $data): User
    {
        $roleNames = $data['roles'] ?? [];

        if (in_array('super-admin', $roleNames, true)) {
            throw new AccessDeniedHttpException(
                'The Super Admin role cannot be assigned through staff management.',
            );
        }

        return DB::transaction(function () use ($data, $roleNames): User {
            $staff = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'account_type' => AccountType::Staff,
            ]);

            if (! empty($roleNames)) {
                $staff->syncRoles($roleNames);
            }

            return $staff->fresh(['roles']);
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, phone?: string|null, is_active?: bool, roles?: array<int, string>}  $data
     */
    public function updateStaff(User $actor, User $staff, array $data): User
    {
        $this->ensureValidStaff($staff);

        $isTargetSuperAdmin = $staff->hasRole('super-admin');

        // Protect Super Admin from being modified by others
        if ($isTargetSuperAdmin && ! $actor->is($staff)) {
            throw new AccessDeniedHttpException('The Super Admin account cannot be modified by other staff.');
        }

        // Prevent deactivating Super Admin
        if ($isTargetSuperAdmin && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw new AccessDeniedHttpException('The Super Admin account cannot be deactivated.');
        }

        // Prevent self-deactivation
        if ($actor->is($staff) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw new AccessDeniedHttpException('You cannot deactivate your own account.');
        }

        // Handle role assignment protections
        if (array_key_exists('roles', $data)) {
            if ($isTargetSuperAdmin) {
                throw new AccessDeniedHttpException('The Super Admin role assignments cannot be modified.');
            }

            if ($actor->is($staff)) {
                throw new AccessDeniedHttpException('You cannot change your own role assignments.');
            }

            if (in_array('super-admin', $data['roles'], true)) {
                throw new AccessDeniedHttpException('The Super Admin role cannot be assigned.');
            }
        }

        return DB::transaction(function () use ($staff, $data): User {
            $fillableData = Arr::only($data, ['name', 'email', 'phone', 'is_active']);

            if (! empty($data['password'])) {
                $fillableData['password'] = Hash::make($data['password']);
            }

            $staff->fill($fillableData)->save();

            if (array_key_exists('roles', $data)) {
                $staff->syncRoles($data['roles']);
            }

            return $staff->fresh(['roles']);
        });
    }

    public function toggleStatus(User $actor, User $staff, bool $isActive): User
    {
        $this->ensureValidStaff($staff);

        if ($staff->hasRole('super-admin')) {
            throw new AccessDeniedHttpException('The Super Admin account cannot be deactivated.');
        }

        if ($actor->is($staff) && ! $isActive) {
            throw new AccessDeniedHttpException('You cannot deactivate your own account.');
        }

        $staff->update(['is_active' => $isActive]);

        return $staff->fresh(['roles']);
    }

    public function deleteStaff(User $actor, User $staff): void
    {
        $this->ensureValidStaff($staff);

        if ($staff->hasRole('super-admin')) {
            throw new AccessDeniedHttpException('The Super Admin account cannot be deleted.');
        }

        if ($actor->is($staff)) {
            throw new AccessDeniedHttpException('You cannot delete your own account.');
        }

        $staff->delete();
    }

    private function ensureValidStaff(User $staff): void
    {
        if (! $staff->isStaff()) {
            throw new NotFoundHttpException('Staff member not found.');
        }
    }
}

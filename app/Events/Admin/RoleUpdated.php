<?php

namespace App\Events\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>|null  $permissions
     */
    public function __construct(
        public Role $role,
        public ?array $permissions = null,
        public bool $permissionsChanged = false,
        public ?User $actor = null,
    ) {}
}

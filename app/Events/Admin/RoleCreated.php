<?php

namespace App\Events\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public Role $role,
        public array $permissions = [],
        public ?User $actor = null,
    ) {}
}

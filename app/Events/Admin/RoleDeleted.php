<?php

namespace App\Events\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Role $role,
        public ?User $actor = null,
    ) {}
}

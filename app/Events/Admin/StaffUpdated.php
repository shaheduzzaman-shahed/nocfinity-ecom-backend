<?php

namespace App\Events\Admin;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>|null  $roles
     */
    public function __construct(
        public User $staff,
        public ?array $roles = null,
        public ?User $actor = null,
    ) {}
}

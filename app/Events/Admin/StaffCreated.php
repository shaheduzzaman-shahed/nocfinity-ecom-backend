<?php

namespace App\Events\Admin;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public User $staff,
        public array $roles = [],
        public ?User $actor = null,
    ) {}
}

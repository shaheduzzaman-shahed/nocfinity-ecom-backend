<?php

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $updatedFields
     */
    public function __construct(
        public User $user,
        public array $updatedFields = [],
        public bool $isStaff = false,
    ) {}
}

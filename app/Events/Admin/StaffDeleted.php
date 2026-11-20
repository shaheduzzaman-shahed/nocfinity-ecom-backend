<?php

namespace App\Events\Admin;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $staff,
        public ?User $actor = null,
    ) {}
}

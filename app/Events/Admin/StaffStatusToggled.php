<?php

namespace App\Events\Admin;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffStatusToggled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $staff,
        public bool $isActive,
        public ?User $actor = null,
    ) {}
}

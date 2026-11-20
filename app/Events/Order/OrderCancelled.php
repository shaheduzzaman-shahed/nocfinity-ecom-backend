<?php

namespace App\Events\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $reason = null,
        public string $cancelledBy = 'customer',
        public ?User $user = null,
    ) {}
}

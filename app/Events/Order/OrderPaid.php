<?php

namespace App\Events\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public float $amount,
        public string $chargeId,
        public ?User $user = null,
    ) {}
}

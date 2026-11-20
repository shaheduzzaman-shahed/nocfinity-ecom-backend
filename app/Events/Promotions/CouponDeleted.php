<?php

namespace App\Events\Promotions;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Coupon $coupon,
        public ?User $actor = null,
    ) {}
}

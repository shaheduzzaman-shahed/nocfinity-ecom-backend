<?php

namespace App\Events\Customer;

use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShippingAddressUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ShippingAddress $address,
        public User $user,
    ) {}
}

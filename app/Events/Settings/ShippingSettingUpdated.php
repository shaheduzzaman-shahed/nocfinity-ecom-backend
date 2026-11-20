<?php

namespace App\Events\Settings;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShippingSettingUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public array $settings,
        public ?User $actor = null,
    ) {}
}

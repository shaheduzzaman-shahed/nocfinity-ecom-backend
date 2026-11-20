<?php

namespace App\Events\Settings;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CurrencySettingUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $currency,
        public ?User $actor = null,
    ) {}
}

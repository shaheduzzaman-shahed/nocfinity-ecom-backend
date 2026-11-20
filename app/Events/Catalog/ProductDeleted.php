<?php

namespace App\Events\Catalog;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Product $product,
        public ?User $actor = null,
    ) {}
}

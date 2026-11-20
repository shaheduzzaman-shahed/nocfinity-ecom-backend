<?php

namespace App\Events\Review;

use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewModerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProductReview $review,
        public string $oldStatus,
        public string $newStatus,
        public ?User $actor = null,
    ) {}
}

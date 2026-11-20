<?php

namespace App\Events\Review;

use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProductReview $review,
        public User $user,
    ) {}
}

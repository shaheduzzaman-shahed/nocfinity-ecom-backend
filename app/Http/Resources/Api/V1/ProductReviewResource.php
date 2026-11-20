<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user' => [
                'id' => $this->user?->id ?? $this->user_id,
                'name' => $this->user?->name ?? 'Verified Customer',
            ],
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $statusValue,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'order_id' => $this->order_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

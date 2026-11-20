<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) ? $this->status->value : $this->status;

        $primaryImage = null;
        if ($this->relationLoaded('product') && $this->product) {
            $primaryImage = $this->product->primaryImage?->image_path ?? null;
        }

        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product?->id ?? $this->product_id,
                'name' => $this->product?->name ?? 'Unknown Product',
                'slug' => $this->product?->slug ?? '',
                'primary_image' => $primaryImage,
            ],
            'customer' => [
                'id' => $this->user?->id ?? $this->user_id,
                'name' => $this->user?->name ?? 'Customer',
                'email' => $this->user?->email ?? '',
            ],
            'order_id' => $this->order_id,
            'order_number' => $this->order?->order_number ?? null,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $statusValue,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

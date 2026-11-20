<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageUrl = $this->image_url;
        if ($imageUrl && ! str_starts_with($imageUrl, 'http://') && ! str_starts_with($imageUrl, 'https://')) {
            $imageUrl = Storage::disk('public')->url($imageUrl);
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'image_url' => $imageUrl,
            'sku' => $this->sku,
            'color_name' => $this->color_name,
            'size_name' => $this->size_name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (int) $this->quantity,
            'line_subtotal' => (float) $this->line_subtotal,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

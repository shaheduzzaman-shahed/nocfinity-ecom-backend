<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'product_name',
        'image_url',
        'sku',
        'color_name',
        'size_name',
        'unit_price',
        'quantity',
        'line_subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}

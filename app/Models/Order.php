<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'shipping_address_id',
        'coupon_id',
        'order_status',
        'payment_status',
        'currency',
        'subtotal',
        'product_discount',
        'coupon_discount',
        'shipping_charge',
        'final_total',
        'ship_name',
        'ship_email',
        'ship_phone',
        'ship_address',
        'ship_city',
        'ship_state',
        'ship_postal_code',
        'ship_country',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'product_discount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'shipping_charge' => 'decimal:2',
            'final_total' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}

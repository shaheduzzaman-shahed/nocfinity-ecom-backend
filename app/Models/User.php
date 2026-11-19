<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar', 'account_type', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function isCustomer(): bool
    {
        return $this->account_type === AccountType::Customer;
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }
}

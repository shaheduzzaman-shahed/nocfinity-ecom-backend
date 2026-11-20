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

    /**
     * Resolve roles and permissions against the "web" guard so Sanctum token
     * authentication cannot redirect RBAC lookups to the provider-less
     * "sanctum" guard.
     */
    protected $guard_name = 'web';

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

    public function isSuperAdmin(): bool
    {
        return $this->isStaff() && $this->hasRole('super-admin');
    }

    /**
     * Effective permission names used by the admin panel navigation and
     * permission matrix. The Super Admin role resolves to every permission.
     *
     * @return array<int, string>
     */
    public function effectivePermissionNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        return $this->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
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

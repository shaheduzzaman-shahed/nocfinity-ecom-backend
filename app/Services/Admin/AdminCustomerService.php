<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminCustomerService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCustomers(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('account_type', AccountType::Customer)
            ->withCount('orders')
            ->withSum(['orders as total_spent' => function ($q) {
                $q->where('payment_status', PaymentStatus::Paid);
            }], 'final_total');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['created_at', 'name', 'email', 'orders_count', 'total_spent'], true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    public function getCustomer(int|string $id): User
    {
        return User::query()
            ->where('account_type', AccountType::Customer)
            ->withCount('orders')
            ->withSum(['orders as total_spent' => function ($q) {
                $q->where('payment_status', PaymentStatus::Paid);
            }], 'final_total')
            ->with(['shippingAddresses'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCustomerOrders(User $customer, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $customer->orders()
            ->with(['items', 'shippingAddress', 'coupon'])
            ->latest()
            ->paginate($perPage);
    }
}

<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $todayStart = Carbon::today();

        $todayPaidOrdersQuery = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('created_at', '>=', $todayStart);

        $todayOrders = (int) $todayPaidOrdersQuery->count();
        $todayRevenue = round((float) $todayPaidOrdersQuery->sum('final_total'), 2);

        $totalCustomers = (int) User::query()
            ->where('account_type', AccountType::Customer)
            ->count();

        $lowStockCount = (int) ProductVariant::query()
            ->where('stock', '<=', 5)
            ->whereHas('product', function ($q) {
                $q->where('is_active', true);
            })
            ->count();

        $storeCurrency = AppSetting::query()
            ->where('setting_key', 'store_currency')
            ->value('setting_value') ?? 'USD';

        return [
            'today_orders' => $todayOrders,
            'today_revenue' => $todayRevenue,
            'total_customers' => $totalCustomers,
            'low_stock_count' => $lowStockCount,
            'store_currency' => $storeCurrency,
        ];
    }

    /**
     * Trailing daily revenue and order volume.
     *
     * @return array<string, mixed>
     */
    public function getSalesChart(int $days = 30): array
    {
        $days = min(max($days, 7), 90);
        $today = Carbon::today();
        $startDate = $today->copy()->subDays($days - 1);

        // Fetch grouped order aggregates from database
        $dailyAggregates = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('created_at', '>=', $startDate->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as orders_count, SUM(final_total) as revenue')
            ->groupBy('order_date')
            ->get()
            ->keyBy('order_date');

        $chartPoints = [];
        $totalRevenue = 0.0;
        $totalOrders = 0;

        for ($i = 0; $i < $days; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dateString = $currentDate->format('Y-m-d');
            $label = $currentDate->format('M d');

            $aggregate = $dailyAggregates->get($dateString);
            $ordersCount = $aggregate ? (int) $aggregate->orders_count : 0;
            $revenue = $aggregate ? round((float) $aggregate->revenue, 2) : 0.0;

            $totalRevenue += $revenue;
            $totalOrders += $ordersCount;

            $chartPoints[] = [
                'date' => $dateString,
                'label' => $label,
                'orders_count' => $ordersCount,
                'revenue' => $revenue,
            ];
        }

        $totalRevenue = round($totalRevenue, 2);
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0;

        $storeCurrency = AppSetting::query()
            ->where('setting_key', 'store_currency')
            ->value('setting_value') ?? 'USD';

        return [
            'period_days' => $days,
            'store_currency' => $storeCurrency,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'average_order_value' => $avgOrderValue,
            ],
            'points' => $chartPoints,
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    public function getRecentOrders(int $limit = 6): Collection
    {
        $limit = min(max($limit, 1), 20);

        return Order::query()
            ->with(['user', 'items'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}

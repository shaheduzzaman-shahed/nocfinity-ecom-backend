<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusUpdated;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminOrderService
{
    /**
     * Paginate orders with search, filtering, and eager-loaded relationships.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['user:id,name,email,phone', 'items', 'coupon', 'payment', 'shippingAddress']);

        // Search by order number, customer name, customer email, ship name, or ship email
        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                    ->orWhere('ship_name', 'like', $term)
                    ->orWhere('ship_email', 'like', $term)
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }

        // Filter by order status
        if (! empty($filters['status'])) {
            $query->where('order_status', $filters['status']);
        }

        // Filter by payment status
        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        // Sorting
        $sortBy = in_array($filters['sort_by'] ?? null, ['created_at', 'final_total', 'order_number'], true)
            ? $filters['sort_by']
            : 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection);

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Retrieve single order details.
     */
    public function getOrder(string|int $id): Order
    {
        return Order::query()
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id)->orWhere('order_number', $id);
                } else {
                    $q->where('order_number', $id);
                }
            })
            ->with(['user:id,name,email,phone', 'items', 'coupon', 'payment', 'shippingAddress'])
            ->firstOrFail();
    }

    /**
     * Update order status following the strict state machine:
     * - Pending -> Cancelled
     * - Paid -> Processing, Cancelled
     * - Processing -> Shipped, Cancelled
     * - Shipped -> Delivered
     * - Cancelled, Delivered -> Terminal (cannot change)
     * - Pending -> Paid -> Prohibited manually (webhook only)
     * - Cancelling Paid or Processing orders restores variant stock transactionally.
     *
     * @throws ValidationException
     */
    public function updateStatus(
        string|int $id,
        string $targetStatus,
        ?string $reason = null,
        ?string $note = null
    ): Order {
        return DB::transaction(function () use ($id, $targetStatus, $reason, $note) {
            /** @var Order $order */
            $order = Order::query()
                ->where(function ($q) use ($id) {
                    if (is_numeric($id)) {
                        $q->where('id', (int) $id)->orWhere('order_number', $id);
                    } else {
                        $q->where('order_number', $id);
                    }
                })
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = is_object($order->order_status)
                ? $order->order_status->value
                : $order->order_status;

            if ($currentStatus === $targetStatus) {
                return $order->fresh(['user', 'items', 'coupon', 'payment', 'shippingAddress']);
            }

            // Terminal status guards
            if ($currentStatus === OrderStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled orders are in a terminal state and cannot be modified.',
                ]);
            }

            if ($currentStatus === OrderStatus::Delivered->value) {
                throw ValidationException::withMessages([
                    'status' => 'Delivered orders are in a terminal state and cannot be modified.',
                ]);
            }

            // Manual payment status transition prohibited
            if ($targetStatus === OrderStatus::Paid->value) {
                throw ValidationException::withMessages([
                    'status' => 'Order payment status can only be confirmed via payment provider webhook.',
                ]);
            }

            // Permitted transitions
            $permitted = match ($currentStatus) {
                OrderStatus::Pending->value => [OrderStatus::Cancelled->value],
                OrderStatus::Paid->value => [OrderStatus::Processing->value, OrderStatus::Cancelled->value],
                OrderStatus::Processing->value => [OrderStatus::Shipped->value, OrderStatus::Cancelled->value],
                OrderStatus::Shipped->value => [OrderStatus::Delivered->value],
                default => [],
            };

            if (! in_array($targetStatus, $permitted, true)) {
                $permittedList = empty($permitted) ? 'none' : implode(', ', $permitted);
                throw ValidationException::withMessages([
                    'status' => "Invalid status transition from {$currentStatus} to {$targetStatus}. Permitted next status: {$permittedList}.",
                ]);
            }

            // Execute transition
            if ($targetStatus === OrderStatus::Cancelled->value) {
                // Restore stock if previously deducted (Paid or Processing)
                if (in_array($currentStatus, [OrderStatus::Paid->value, OrderStatus::Processing->value], true)) {
                    foreach ($order->items as $item) {
                        if ($item->product_variant_id) {
                            $variant = ProductVariant::query()
                                ->where('id', $item->product_variant_id)
                                ->lockForUpdate()
                                ->first();

                            if ($variant) {
                                $variant->increment('stock', $item->quantity);
                            }
                        }
                    }
                }

                $order->update([
                    'order_status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

                // Dispatch cancellation email
                try {
                    if ($order->ship_email) {
                        Mail::to($order->ship_email)->send(
                            new OrderCancelledMail($order, $reason ?? $note ?? 'Order cancelled by store administration.')
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to send admin cancellation email for order {$order->order_number}: {$e->getMessage()}");
                }
            } else {
                // Forward lifecycle progression
                $order->update([
                    'order_status' => OrderStatus::from($targetStatus),
                ]);

                // Dispatch status update email
                try {
                    if ($order->ship_email) {
                        Mail::to($order->ship_email)->send(
                            new OrderStatusUpdatedMail($order, $currentStatus, $targetStatus, $note ?? $reason)
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to send status update email for order {$order->order_number}: {$e->getMessage()}");
                }
            }

            OrderStatusUpdated::dispatch($order, $currentStatus, $targetStatus, $reason, $note);

            return $order->fresh(['user', 'items', 'coupon', 'payment', 'shippingAddress']);
        });
    }
}

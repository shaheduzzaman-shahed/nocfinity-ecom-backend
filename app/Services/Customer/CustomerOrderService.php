<?php

namespace App\Services\Customer;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCancelled;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CustomerOrderService
{
    /**
     * List paginated orders for a customer.
     */
    public function listOrders(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with(['items', 'coupon', 'payment', 'shippingAddress'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Retrieve single order details for a customer with IDOR verification.
     */
    public function getOrder(User $user, string|int $id): Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id)->orWhere('order_number', $id);
                } else {
                    $q->where('order_number', $id);
                }
            })
            ->with(['items', 'coupon', 'payment', 'shippingAddress'])
            ->firstOrFail();
    }

    /**
     * Cancel an order for a customer enforcing lifecycle & inventory restoration rules.
     *
     * Rules:
     * - Customer can cancel Pending (unpaid) orders: no inventory restoration since stock was not deducted.
     * - Customer can cancel Paid orders: restores stock within an atomic transaction.
     * - Customer cannot cancel Processing, Shipped, Delivered orders.
     * - Cancelled orders cannot be cancelled again (idempotent guard).
     *
     * @throws ValidationException
     */
    public function cancelOrder(User $user, string|int $id, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($user, $id, $reason) {
            /** @var Order $order */
            $order = Order::query()
                ->where('user_id', $user->id)
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

            if ($order->order_status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'Order has already been cancelled.',
                ]);
            }

            if (in_array($order->order_status, [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered], true)) {
                throw ValidationException::withMessages([
                    'order' => "Order cannot be cancelled once it is {$order->order_status->value}.",
                ]);
            }

            // If the order was Paid, restore inventory atomically with row locks
            if ($order->order_status === OrderStatus::Paid) {
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

            // Update order status and cancellation timestamp
            $order->update([
                'order_status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            // Attempt to dispatch order cancellation email
            try {
                if ($order->ship_email) {
                    Mail::to($order->ship_email)->send(
                        new OrderCancelledMail($order, $reason ?? 'Cancelled upon customer request.')
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to send cancellation email for order {$order->order_number}: {$e->getMessage()}");
            }

            OrderCancelled::dispatch($order, $reason, 'customer', $user);

            return $order->fresh(['items', 'coupon', 'payment', 'shippingAddress']);
        });
    }
}

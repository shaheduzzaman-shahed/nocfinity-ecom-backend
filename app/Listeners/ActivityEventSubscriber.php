<?php

namespace App\Listeners;

use App\Events\Admin\RoleCreated;
use App\Events\Admin\RoleDeleted;
use App\Events\Admin\RoleUpdated;
use App\Events\Admin\StaffCreated;
use App\Events\Admin\StaffDeleted;
use App\Events\Admin\StaffStatusToggled;
use App\Events\Admin\StaffUpdated;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserPasswordReset;
use App\Events\Auth\UserPasswordUpdated;
use App\Events\Auth\UserProfileUpdated;
use App\Events\Auth\UserRegistered;
use App\Events\Catalog\ProductCreated;
use App\Events\Catalog\ProductDeleted;
use App\Events\Catalog\ProductUpdated;
use App\Events\Customer\ShippingAddressCreated;
use App\Events\Customer\ShippingAddressDeleted;
use App\Events\Customer\ShippingAddressUpdated;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderPaid;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Promotions\CouponCreated;
use App\Events\Promotions\CouponDeleted;
use App\Events\Promotions\CouponUpdated;
use App\Events\Review\ReviewDeleted;
use App\Events\Review\ReviewModerated;
use App\Events\Review\ReviewSubmitted;
use App\Events\Settings\CurrencySettingUpdated;
use App\Events\Settings\ShippingSettingUpdated;
use App\Services\ActivityLog\ActivityLoggerService;
use Illuminate\Events\Dispatcher;

class ActivityEventSubscriber
{
    public function __construct(
        protected ActivityLoggerService $logger,
    ) {}

    public function handleUserRegistered(UserRegistered $event): void
    {
        $this->logger->log(
            action: 'auth.registered',
            entity: $event->user,
            payload: [
                'email' => $event->user->email,
                'name' => $event->user->name,
            ],
            user: $event->user,
        );
    }

    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $this->logger->log(
            action: $event->isStaff ? 'staff.auth.login' : 'auth.login',
            entity: $event->user,
            payload: ['email' => $event->user->email],
            user: $event->user,
            ipAddress: $event->ipAddress,
        );
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        $this->logger->log(
            action: $event->isStaff ? 'staff.auth.logout' : 'auth.logout',
            entity: $event->user,
            user: $event->user,
            ipAddress: $event->ipAddress,
        );
    }

    public function handleUserProfileUpdated(UserProfileUpdated $event): void
    {
        $this->logger->log(
            action: $event->isStaff ? 'staff.profile.updated' : 'profile.updated',
            entity: $event->user,
            payload: ['updated_fields' => $event->updatedFields],
            user: $event->user,
        );
    }

    public function handleUserPasswordUpdated(UserPasswordUpdated $event): void
    {
        $this->logger->log(
            action: $event->isStaff ? 'staff.password.updated' : 'auth.password.updated',
            entity: $event->user,
            user: $event->user,
        );
    }

    public function handleUserPasswordReset(UserPasswordReset $event): void
    {
        $this->logger->log(
            action: 'auth.password_reset',
            entity: $event->user,
            payload: ['email' => $event->user->email],
            user: $event->user,
        );
    }

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $this->logger->log(
            action: 'orders.placed',
            entity: $event->order,
            payload: [
                'order_number' => $event->order->order_number,
                'final_total' => (float) $event->order->final_total,
                'currency' => $event->order->currency,
            ],
            user: $event->user,
        );
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        $this->logger->log(
            action: 'orders.paid',
            entity: $event->order,
            payload: [
                'order_number' => $event->order->order_number,
                'amount' => $event->amount,
                'charge_id' => $event->chargeId,
            ],
            user: $event->user,
        );
    }

    public function handleOrderStatusUpdated(OrderStatusUpdated $event): void
    {
        $this->logger->log(
            action: 'orders.status_updated',
            entity: $event->order,
            payload: [
                'order_number' => $event->order->order_number,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'reason' => $event->reason,
                'note' => $event->note,
            ],
            user: $event->actor,
        );
    }

    public function handleOrderCancelled(OrderCancelled $event): void
    {
        $this->logger->log(
            action: 'orders.cancelled',
            entity: $event->order,
            payload: [
                'order_number' => $event->order->order_number,
                'reason' => $event->reason ?? 'Cancelled upon request.',
                'cancelled_by' => $event->cancelledBy,
            ],
            user: $event->user,
        );
    }

    public function handleProductCreated(ProductCreated $event): void
    {
        $this->logger->log(
            action: 'products.created',
            entity: $event->product,
            payload: [
                'name' => $event->product->name,
                'slug' => $event->product->slug,
                'regular_price' => (float) $event->product->regular_price,
            ],
            user: $event->actor,
        );
    }

    public function handleProductUpdated(ProductUpdated $event): void
    {
        $this->logger->log(
            action: 'products.updated',
            entity: $event->product,
            payload: [
                'name' => $event->product->name,
                'slug' => $event->product->slug,
                'is_active' => (bool) $event->product->is_active,
            ],
            user: $event->actor,
        );
    }

    public function handleProductDeleted(ProductDeleted $event): void
    {
        $this->logger->log(
            action: 'products.deleted',
            entity: $event->product,
            payload: [
                'name' => $event->product->name,
                'slug' => $event->product->slug,
            ],
            user: $event->actor,
        );
    }

    public function handleRoleCreated(RoleCreated $event): void
    {
        $this->logger->log(
            action: 'roles.created',
            entity: $event->role,
            payload: [
                'name' => $event->role->name,
                'display_name' => $event->role->display_name,
                'permissions' => $event->permissions,
            ],
            user: $event->actor,
        );
    }

    public function handleRoleUpdated(RoleUpdated $event): void
    {
        $action = $event->permissionsChanged ? 'roles.permissions_modified' : 'roles.updated';
        $this->logger->log(
            action: $action,
            entity: $event->role,
            payload: [
                'name' => $event->role->name,
                'display_name' => $event->role->display_name,
                'permissions' => $event->permissions,
            ],
            user: $event->actor,
        );
    }

    public function handleRoleDeleted(RoleDeleted $event): void
    {
        $this->logger->log(
            action: 'roles.deleted',
            entity: $event->role,
            payload: [
                'name' => $event->role->name,
                'display_name' => $event->role->display_name,
            ],
            user: $event->actor,
        );
    }

    public function handleStaffCreated(StaffCreated $event): void
    {
        $this->logger->log(
            action: 'staff.created',
            entity: $event->staff,
            payload: [
                'name' => $event->staff->name,
                'email' => $event->staff->email,
                'roles' => $event->roles,
            ],
            user: $event->actor,
        );
    }

    public function handleStaffUpdated(StaffUpdated $event): void
    {
        $this->logger->log(
            action: 'staff.updated',
            entity: $event->staff,
            payload: [
                'name' => $event->staff->name,
                'email' => $event->staff->email,
                'roles' => $event->roles,
            ],
            user: $event->actor,
        );
    }

    public function handleStaffStatusToggled(StaffStatusToggled $event): void
    {
        $this->logger->log(
            action: 'staff.status_toggled',
            entity: $event->staff,
            payload: ['is_active' => $event->isActive],
            user: $event->actor,
        );
    }

    public function handleStaffDeleted(StaffDeleted $event): void
    {
        $this->logger->log(
            action: 'staff.deleted',
            entity: $event->staff,
            payload: [
                'name' => $event->staff->name,
                'email' => $event->staff->email,
            ],
            user: $event->actor,
        );
    }

    public function handleCouponCreated(CouponCreated $event): void
    {
        $this->logger->log(
            action: 'coupons.created',
            entity: $event->coupon,
            payload: [
                'code' => $event->coupon->code,
                'type' => $event->coupon->type,
                'value' => (float) $event->coupon->value,
            ],
            user: $event->actor,
        );
    }

    public function handleCouponUpdated(CouponUpdated $event): void
    {
        $this->logger->log(
            action: 'coupons.updated',
            entity: $event->coupon,
            payload: [
                'code' => $event->coupon->code,
                'type' => $event->coupon->type,
                'value' => (float) $event->coupon->value,
                'is_active' => (bool) $event->coupon->is_active,
            ],
            user: $event->actor,
        );
    }

    public function handleCouponDeleted(CouponDeleted $event): void
    {
        $this->logger->log(
            action: 'coupons.deleted',
            entity: $event->coupon,
            payload: ['code' => $event->coupon->code],
            user: $event->actor,
        );
    }

    public function handleShippingSettingUpdated(ShippingSettingUpdated $event): void
    {
        $this->logger->log(
            action: 'settings.shipping_updated',
            payload: $event->settings,
            user: $event->actor,
        );
    }

    public function handleCurrencySettingUpdated(CurrencySettingUpdated $event): void
    {
        $this->logger->log(
            action: 'settings.currency_updated',
            payload: ['currency' => $event->currency],
            user: $event->actor,
        );
    }

    public function handleReviewSubmitted(ReviewSubmitted $event): void
    {
        $this->logger->log(
            action: 'reviews.submitted',
            entity: $event->review,
            payload: [
                'product_id' => $event->review->product_id,
                'rating' => (int) $event->review->rating,
            ],
            user: $event->user,
        );
    }

    public function handleReviewModerated(ReviewModerated $event): void
    {
        $this->logger->log(
            action: 'reviews.moderated',
            entity: $event->review,
            payload: [
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'product_id' => $event->review->product_id,
            ],
            user: $event->actor,
        );
    }

    public function handleReviewDeleted(ReviewDeleted $event): void
    {
        $this->logger->log(
            action: 'reviews.deleted',
            entity: $event->review,
            payload: [
                'product_id' => $event->review->product_id,
                'rating' => $event->review->rating,
            ],
            user: $event->actor,
        );
    }

    public function handleShippingAddressCreated(ShippingAddressCreated $event): void
    {
        $this->logger->log(
            action: 'shipping_address.created',
            entity: $event->address,
            payload: [
                'name' => $event->address->name,
                'city' => $event->address->city,
                'country' => $event->address->country,
            ],
            user: $event->user,
        );
    }

    public function handleShippingAddressUpdated(ShippingAddressUpdated $event): void
    {
        $this->logger->log(
            action: 'shipping_address.updated',
            entity: $event->address,
            payload: [
                'name' => $event->address->name,
                'city' => $event->address->city,
                'country' => $event->address->country,
            ],
            user: $event->user,
        );
    }

    public function handleShippingAddressDeleted(ShippingAddressDeleted $event): void
    {
        $this->logger->log(
            action: 'shipping_address.deleted',
            entity: $event->address,
            payload: [
                'name' => $event->address->name,
                'city' => $event->address->city,
            ],
            user: $event->user,
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserRegistered::class => 'handleUserRegistered',
            UserLoggedIn::class => 'handleUserLoggedIn',
            UserLoggedOut::class => 'handleUserLoggedOut',
            UserProfileUpdated::class => 'handleUserProfileUpdated',
            UserPasswordUpdated::class => 'handleUserPasswordUpdated',
            UserPasswordReset::class => 'handleUserPasswordReset',
            OrderPlaced::class => 'handleOrderPlaced',
            OrderPaid::class => 'handleOrderPaid',
            OrderStatusUpdated::class => 'handleOrderStatusUpdated',
            OrderCancelled::class => 'handleOrderCancelled',
            ProductCreated::class => 'handleProductCreated',
            ProductUpdated::class => 'handleProductUpdated',
            ProductDeleted::class => 'handleProductDeleted',
            RoleCreated::class => 'handleRoleCreated',
            RoleUpdated::class => 'handleRoleUpdated',
            RoleDeleted::class => 'handleRoleDeleted',
            StaffCreated::class => 'handleStaffCreated',
            StaffUpdated::class => 'handleStaffUpdated',
            StaffStatusToggled::class => 'handleStaffStatusToggled',
            StaffDeleted::class => 'handleStaffDeleted',
            CouponCreated::class => 'handleCouponCreated',
            CouponUpdated::class => 'handleCouponUpdated',
            CouponDeleted::class => 'handleCouponDeleted',
            ShippingSettingUpdated::class => 'handleShippingSettingUpdated',
            CurrencySettingUpdated::class => 'handleCurrencySettingUpdated',
            ReviewSubmitted::class => 'handleReviewSubmitted',
            ReviewModerated::class => 'handleReviewModerated',
            ReviewDeleted::class => 'handleReviewDeleted',
            ShippingAddressCreated::class => 'handleShippingAddressCreated',
            ShippingAddressUpdated::class => 'handleShippingAddressUpdated',
            ShippingAddressDeleted::class => 'handleShippingAddressDeleted',
        ];
    }
}

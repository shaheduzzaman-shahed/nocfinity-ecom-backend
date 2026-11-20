<?php

namespace App\Services\Promotions;

use App\Events\Promotions\CouponCreated;
use App\Events\Promotions\CouponDeleted;
use App\Events\Promotions\CouponUpdated;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Coupon::query();

        if (! empty($filters['search'])) {
            $search = strtoupper(trim((string) $filters['search']));
            $query->where('code', 'like', "%{$search}%");
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $allowedSorts = ['id', 'code', 'type', 'value', 'usage_count', 'valid_from', 'valid_until', 'is_active', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection);

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    public function find(int $id): Coupon
    {
        return Coupon::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        }

        $coupon = Coupon::create($data);

        CouponCreated::dispatch($coupon);

        return $coupon;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        }

        $coupon->update($data);

        CouponUpdated::dispatch($coupon);

        return $coupon->fresh();
    }

    public function delete(Coupon $coupon): void
    {
        CouponDeleted::dispatch($coupon);

        $coupon->delete();
    }

    /**
     * Validate coupon rules against subtotal and customer redemption history.
     *
     * @throws ValidationException
     */
    public function validateCoupon(string $code, ?User $user, float $subtotal): Coupon
    {
        $normalizedCode = strtoupper(trim($code));

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', $normalizedCode)->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'code' => 'The coupon code is invalid.',
            ]);
        }

        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'code' => 'This coupon is currently inactive.',
            ]);
        }

        $now = now();

        if ($coupon->valid_from !== null && $now->lt($coupon->valid_from)) {
            throw ValidationException::withMessages([
                'code' => 'This coupon is not yet valid.',
            ]);
        }

        if ($coupon->valid_until !== null && $now->gt($coupon->valid_until)) {
            throw ValidationException::withMessages([
                'code' => 'This coupon has expired.',
            ]);
        }

        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'code' => 'This coupon has reached its maximum usage limit.',
            ]);
        }

        if ($user !== null) {
            $alreadyRedeemed = CouponUser::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyRedeemed) {
                throw ValidationException::withMessages([
                    'code' => 'You have already redeemed this coupon.',
                ]);
            }
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            $minAmountFormatted = number_format((float) $coupon->min_order_amount, 2);
            throw ValidationException::withMessages([
                'code' => "A minimum order subtotal of \${$minAmountFormatted} is required to apply this coupon.",
            ]);
        }

        return $coupon;
    }

    /**
     * Apply coupon to customer cart.
     *
     * @return array{cart: Cart, discount: float}
     */
    public function applyToCart(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code) {
            $cart = $this->cartService->getOrCreateCustomerCart($user);
            $cart = $this->cartService->loadCartWithRelations($cart);

            $subtotal = 0.0;
            foreach ($cart->items as $item) {
                $variant = $item->productVariant;
                $product = $variant?->product;
                $effectivePrice = (float) (
                    $variant?->price_override
                    ?? $product?->discount_price
                    ?? $product?->regular_price
                    ?? 0.00
                );
                $subtotal += round($effectivePrice * (int) $item->quantity, 2);
            }

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'code' => 'Your cart is empty. Add items before applying a coupon.',
                ]);
            }

            $coupon = $this->validateCoupon($code, $user, $subtotal);

            $cart->coupon_id = $coupon->id;
            $cart->save();

            $cart = $this->cartService->loadCartWithRelations($cart);
            $discount = $coupon->calculateDiscount($subtotal);

            return [
                'cart' => $cart,
                'discount' => $discount,
            ];
        });
    }

    /**
     * Remove coupon from customer cart.
     */
    public function removeFromCart(User $user): Cart
    {
        $cart = $this->cartService->getOrCreateCustomerCart($user);
        $cart->coupon_id = null;
        $cart->save();

        return $this->cartService->loadCartWithRelations($cart);
    }
}

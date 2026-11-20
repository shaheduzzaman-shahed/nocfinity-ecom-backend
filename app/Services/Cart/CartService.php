<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartService
{
    public function getOrCreateCustomerCart(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function loadCartWithRelations(Cart $cart): Cart
    {
        return $cart->load([
            'items' => fn ($q) => $q->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
            'items.productVariant.product.images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order'),
            'items.productVariant.color',
            'items.productVariant.size',
        ]);
    }

    /**
     * @return array{cart: Cart, warnings: array<int, string>}
     */
    public function getCustomerCart(User $user): array
    {
        $cart = $this->getOrCreateCustomerCart($user);
        $this->loadCartWithRelations($cart);

        $warnings = [];

        foreach ($cart->items as $item) {
            $variant = $item->productVariant;
            $product = $variant?->product;

            if (! $variant || ! $product || ! $variant->is_active || ! $product->is_active) {
                $warnings[] = 'An item in your cart is currently unavailable.';
            } elseif ($variant->stock <= 0) {
                $warnings[] = "The item '{$product->name}' is currently out of stock.";
            } elseif ($item->quantity > $variant->stock) {
                $warnings[] = "Requested quantity for '{$product->name}' exceeds available stock ({$variant->stock} in stock).";
            }
        }

        return [
            'cart' => $cart,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return array{cart: Cart, warnings: array<int, string>}
     */
    public function addItem(User $user, int $variantId, int $quantity): array
    {
        return DB::transaction(function () use ($user, $variantId, $quantity): array {
            $cart = $this->getOrCreateCustomerCart($user);

            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->with('product')->find($variantId);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected product variant does not exist.',
                ]);
            }

            $product = $variant->product;

            if (! $variant->is_active || ! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'This product is currently inactive and cannot be added to your cart.',
                ]);
            }

            if ($variant->stock <= 0) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'This item is currently out of stock.',
                ]);
            }

            $existingItem = $cart->items()->where('product_variant_id', $variant->id)->first();

            $warnings = [];

            if ($existingItem) {
                $newTotal = $existingItem->quantity + $quantity;

                if ($newTotal > $variant->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => "The requested quantity exceeds available stock ({$variant->stock} available, {$existingItem->quantity} already in cart).",
                    ]);
                }

                $existingItem->update(['quantity' => $newTotal]);
            } else {
                if ($quantity > $variant->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => "The requested quantity exceeds available stock ({$variant->stock} available).",
                    ]);
                }

                $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                ]);
            }

            $this->loadCartWithRelations($cart);

            return [
                'cart' => $cart,
                'warnings' => $warnings,
            ];
        });
    }

    public function updateItem(User $user, int $cartItemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $cartItemId, $quantity): Cart {
            $cart = $this->getOrCreateCustomerCart($user);

            /** @var CartItem|null $item */
            $item = $cart->items()->where('id', $cartItemId)->first();

            if (! $item) {
                throw new NotFoundHttpException('Cart item not found.');
            }

            $variant = $item->productVariant()->with('product')->first();

            if (! $variant || ! $variant->product || ! $variant->is_active || ! $variant->product->is_active) {
                throw ValidationException::withMessages([
                    'quantity' => 'This product is no longer active.',
                ]);
            }

            if ($quantity > $variant->stock) {
                throw ValidationException::withMessages([
                    'quantity' => "The requested quantity exceeds available stock ({$variant->stock} available).",
                ]);
            }

            $item->update(['quantity' => $quantity]);

            return $this->loadCartWithRelations($cart);
        });
    }

    public function removeItem(User $user, int $cartItemId): Cart
    {
        return DB::transaction(function () use ($user, $cartItemId): Cart {
            $cart = $this->getOrCreateCustomerCart($user);

            /** @var CartItem|null $item */
            $item = $cart->items()->where('id', $cartItemId)->first();

            if (! $item) {
                throw new NotFoundHttpException('Cart item not found.');
            }

            $item->delete();

            return $this->loadCartWithRelations($cart);
        });
    }

    public function clearCart(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $cart = $this->getOrCreateCustomerCart($user);
            $cart->items()->delete();

            return $this->loadCartWithRelations($cart);
        });
    }

    /**
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $guestItems
     * @return array{cart: Cart, warnings: array<int, string>}
     */
    public function mergeGuestCart(User $user, array $guestItems): array
    {
        return DB::transaction(function () use ($user, $guestItems): array {
            $cart = $this->getOrCreateCustomerCart($user);
            $warnings = [];

            foreach ($guestItems as $raw) {
                $variantId = (int) ($raw['product_variant_id'] ?? 0);
                $guestQty = (int) ($raw['quantity'] ?? 0);

                if ($variantId <= 0 || $guestQty <= 0) {
                    continue;
                }

                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()->with('product')->find($variantId);

                if (! $variant || ! $variant->product || ! $variant->is_active || ! $variant->product->is_active) {
                    $warnings[] = 'An item from your guest cart was discontinued and could not be merged.';

                    continue;
                }

                if ($variant->stock <= 0) {
                    $warnings[] = "The item '{$variant->product->name}' is out of stock and was not added.";

                    continue;
                }

                $existingItem = $cart->items()->where('product_variant_id', $variant->id)->first();

                if ($existingItem) {
                    $combinedQty = $existingItem->quantity + $guestQty;

                    if ($combinedQty > $variant->stock) {
                        $existingItem->update(['quantity' => $variant->stock]);
                        $warnings[] = "Quantity for '{$variant->product->name}' was adjusted to available stock ({$variant->stock}).";
                    } else {
                        $existingItem->update(['quantity' => $combinedQty]);
                    }
                } else {
                    $qtyToInsert = min($guestQty, $variant->stock);

                    if ($guestQty > $variant->stock) {
                        $warnings[] = "Quantity for '{$variant->product->name}' was adjusted to available stock ({$variant->stock}).";
                    }

                    $cart->items()->create([
                        'product_variant_id' => $variant->id,
                        'quantity' => $qtyToInsert,
                    ]);
                }
            }

            $this->loadCartWithRelations($cart);

            return [
                'cart' => $cart,
                'warnings' => array_values(array_unique($warnings)),
            ];
        });
    }
}

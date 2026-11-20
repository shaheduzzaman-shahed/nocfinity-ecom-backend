<?php

namespace App\Services\Customer;

use App\Events\Customer\ShippingAddressCreated;
use App\Events\Customer\ShippingAddressDeleted;
use App\Events\Customer\ShippingAddressUpdated;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingAddressService
{
    /**
     * @return Collection<int, ShippingAddress>
     */
    public function list(User $user): Collection
    {
        return $user->shippingAddresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForUser(User $user, int $addressId): ShippingAddress
    {
        /** @var ShippingAddress $address */
        $address = $user->shippingAddresses()->findOrFail($addressId);

        return $address;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): ShippingAddress
    {
        return DB::transaction(function () use ($user, $data) {
            $isFirstAddress = $user->shippingAddresses()->count() === 0;

            if ($isFirstAddress || ! empty($data['is_default'])) {
                $user->shippingAddresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }

            /** @var ShippingAddress $address */
            $address = $user->shippingAddresses()->create($data);

            ShippingAddressCreated::dispatch($address, $user);

            return $address->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $addressId, array $data): ShippingAddress
    {
        return DB::transaction(function () use ($user, $addressId, $data) {
            $address = $this->findForUser($user, $addressId);

            if (! empty($data['is_default'])) {
                $user->shippingAddresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            $address->update($data);

            ShippingAddressUpdated::dispatch($address, $user);

            return $address->fresh();
        });
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId) {
            $address = $this->findForUser($user, $addressId);

            if ($address->orders()->exists()) {
                throw ValidationException::withMessages([
                    'address' => 'This shipping address cannot be deleted because it is associated with existing orders.',
                ]);
            }

            ShippingAddressDeleted::dispatch($address, $user);

            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                /** @var ShippingAddress|null $fallback */
                $fallback = $user->shippingAddresses()->latest()->first();
                $fallback?->update(['is_default' => true]);
            }
        });
    }

    public function setDefault(User $user, int $addressId): ShippingAddress
    {
        return DB::transaction(function () use ($user, $addressId) {
            $address = $this->findForUser($user, $addressId);

            $user->shippingAddresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }
}

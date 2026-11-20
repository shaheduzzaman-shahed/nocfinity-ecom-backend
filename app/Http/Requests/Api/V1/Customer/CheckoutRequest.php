<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Strictly forbid and reject any client-submitted financials
        $forbiddenKeys = [
            'subtotal',
            'total',
            'final_total',
            'price',
            'unit_price',
            'product_discount',
            'coupon_discount',
            'shipping_charge',
            'shipping_fee',
        ];

        foreach ($forbiddenKeys as $key) {
            if ($this->has($key)) {
                throw ValidationException::withMessages([
                    'financials' => 'Client-submitted pricing, totals, and discounts are strictly forbidden. All monetary calculations are performed authoritatively by the server.',
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_address_id' => ['nullable', 'integer'],
            'name' => ['required_without:shipping_address_id', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:shipping_address_id', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:shipping_address_id', 'nullable', 'string', 'max:50'],
            'address' => ['required_without:shipping_address_id', 'nullable', 'string'],
            'city' => ['required_without:shipping_address_id', 'nullable', 'string', 'max:100'],
            'state' => ['required_without:shipping_address_id', 'nullable', 'string', 'max:100'],
            'postal_code' => ['required_without:shipping_address_id', 'nullable', 'string', 'max:20'],
            'country' => ['required_without:shipping_address_id', 'nullable', 'string', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}

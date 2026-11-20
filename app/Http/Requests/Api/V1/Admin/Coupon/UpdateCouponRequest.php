<?php

namespace App\Http\Requests\Api\V1\Admin\Coupon;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $couponParam = $this->route('coupon');
        $couponId = $couponParam instanceof Coupon ? $couponParam->id : $couponParam;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')->ignore($couponId),
            ],
            'type' => ['sometimes', Rule::in(['percentage', 'fixed'])],
            'value' => [
                'sometimes',
                'numeric',
                'min:0.01',
                Rule::when(
                    $this->input('type', $couponParam instanceof Coupon ? $couponParam->type : null) === 'percentage',
                    ['max:100'],
                ),
            ],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

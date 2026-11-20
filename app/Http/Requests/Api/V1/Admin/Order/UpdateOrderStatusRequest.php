<?php

namespace App\Http\Requests\Api\V1\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['processing', 'shipped', 'delivered', 'cancelled']),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}

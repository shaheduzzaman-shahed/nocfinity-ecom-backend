<?php

namespace App\Http\Requests\Api\V1\Admin\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', 'string', Rule::enum(PaymentStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'final_total', 'order_number'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}

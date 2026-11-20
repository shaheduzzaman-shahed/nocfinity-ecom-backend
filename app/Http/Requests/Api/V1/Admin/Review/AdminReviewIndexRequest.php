<?php

namespace App\Http\Requests\Api\V1\Admin\Review;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminReviewIndexRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::enum(ReviewStatus::class)],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'rating', 'status'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

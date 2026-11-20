<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
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
            'rating' => ['sometimes', 'required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['sometimes', 'required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.between' => 'Star rating must be between 1 and 5.',
            'comment.min' => 'Review comment must be at least 3 characters.',
            'comment.max' => 'Review comment cannot exceed 2000 characters.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Http\Requests\Api\V1\Admin\Product\Concerns\ValidatesProductPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'regular_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            'discount_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->variantRules(true));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateProductPayload($validator));
    }
}

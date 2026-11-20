<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductImageRequest extends FormRequest
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
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'primary_index' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $single = $this->file('image');
            $images = (array) $this->file('images', []);

            if (! $single && $images === []) {
                $validator->errors()->add(
                    'images',
                    'Please choose at least one image to upload.',
                );
            }

            $primaryIndex = $this->input('primary_index');

            if ($primaryIndex !== null && (int) $primaryIndex >= count($images)) {
                $validator->errors()->add(
                    'primary_index',
                    'The primary image index does not match an uploaded image.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.image' => 'Each file must be a valid image.',
            'images.*.mimes' => 'Images must be JPEG, PNG, or WebP files.',
            'images.*.max' => 'Each image may not be larger than 4 MB.',
            'image.image' => 'The file must be a valid image.',
            'image.mimes' => 'The image must be a JPEG, PNG, or WebP file.',
            'image.max' => 'The image may not be larger than 4 MB.',
        ];
    }
}

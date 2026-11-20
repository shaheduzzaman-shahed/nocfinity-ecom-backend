<?php

namespace App\Http\Requests\Api\V1\Admin\Color;

use App\Rules\UniqueCaseInsensitive;
use Illuminate\Foundation\Http\FormRequest;

class StoreColorRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                new UniqueCaseInsensitive(
                    'colors',
                    'name',
                    null,
                    'A color with this name already exists.',
                ),
            ],
            'hex_code' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hex_code.regex' => 'The hex code must be in #RRGGBB format.',
        ];
    }
}

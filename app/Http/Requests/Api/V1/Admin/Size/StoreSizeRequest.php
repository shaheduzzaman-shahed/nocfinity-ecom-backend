<?php

namespace App\Http\Requests\Api\V1\Admin\Size;

use App\Rules\UniqueCaseInsensitive;
use Illuminate\Foundation\Http\FormRequest;

class StoreSizeRequest extends FormRequest
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
                'max:50',
                new UniqueCaseInsensitive(
                    'sizes',
                    'name',
                    null,
                    'A size with this name already exists.',
                ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:99999'],
        ];
    }
}

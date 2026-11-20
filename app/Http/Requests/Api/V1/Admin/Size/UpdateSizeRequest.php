<?php

namespace App\Http\Requests\Api\V1\Admin\Size;

use App\Models\Size;
use App\Rules\UniqueCaseInsensitive;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSizeRequest extends FormRequest
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
        $size = $this->route('size');
        $sizeId = $size instanceof Size ? $size->id : null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                new UniqueCaseInsensitive(
                    'sizes',
                    'name',
                    $sizeId,
                    'A size with this name already exists.',
                ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:99999'],
        ];
    }
}

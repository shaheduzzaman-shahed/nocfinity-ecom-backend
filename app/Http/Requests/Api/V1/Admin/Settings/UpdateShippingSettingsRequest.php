<?php

namespace App\Http\Requests\Api\V1\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingSettingsRequest extends FormRequest
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
            'shipping_fee' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_enabled' => ['sometimes', 'boolean'],
        ];
    }
}

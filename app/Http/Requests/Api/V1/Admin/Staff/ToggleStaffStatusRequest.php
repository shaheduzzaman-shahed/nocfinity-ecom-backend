<?php

namespace App\Http\Requests\Api\V1\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ToggleStaffStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}

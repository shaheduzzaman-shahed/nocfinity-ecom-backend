<?php

namespace App\Http\Requests\Api\V1\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Staff can only update their name through profile management.
     * Email, roles, and permissions are managed elsewhere.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class ActivateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activation_code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'activation_code.required' => 'Aktivatsiya kodini kiriting.',
            'activation_code.digits' => 'Aktivatsiya kodi 6 xonali raqam bo‘lishi kerak.',
        ];
    }
}

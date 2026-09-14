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
            'activation_code' => ['required', 'string', 'size:8', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'activation_code.required' => 'Aktivatsiya kodini kiriting.',
            'activation_code.size' => 'Aktivatsiya kodi 8 ta belgidan iborat bo‘lishi kerak.',
            'activation_code.regex' => 'Aktivatsiya kodi faqat harf va raqamlardan iborat bo‘lishi kerak.',
        ];
    }
}

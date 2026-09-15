<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Mijoz telefonini kiriting.',
            'phone.regex' => 'Telefon raqamini to‘g‘ri kiriting.',
        ];
    }
}

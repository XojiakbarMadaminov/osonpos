<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class SetOrderCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['customer_id' => ['required', 'ulid']];
    }

    public function messages(): array
    {
        return ['customer_id.required' => 'Mijozni tanlang.'];
    }
}

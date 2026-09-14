<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SwitchAdminContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->organizations()->exists() === true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Faol filialni tanlang.',
            'store_id.integer' => 'Tanlangan filial noto‘g‘ri.',
        ];
    }
}

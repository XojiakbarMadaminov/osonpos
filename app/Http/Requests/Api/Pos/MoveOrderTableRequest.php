<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class MoveOrderTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'table_id.required' => 'Ko‘chirish uchun stolni tanlang.',
            'table_id.integer' => 'Tanlangan stol noto‘g‘ri.',
        ];
    }
}

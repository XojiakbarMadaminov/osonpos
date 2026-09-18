<?php

namespace App\Http\Requests\Api\Pos;

use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetOrderDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_type.required' => 'Chegirma turini tanlang.',
            'discount_type.enum' => 'Chegirma turi noto‘g‘ri.',
            'discount_value.required' => 'Chegirma qiymatini kiriting.',
            'discount_value.integer' => 'Chegirma butun son bo‘lishi kerak.',
            'discount_value.min' => 'Chegirma qiymati kamida 1 bo‘lishi kerak.',
        ];
    }
}

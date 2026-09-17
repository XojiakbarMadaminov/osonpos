<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CreateMixedPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_payment_id' => ['required', 'ulid', 'different:card_payment_id'],
            'cash_amount' => ['required', 'integer', 'min:1'],
            'card_payment_id' => ['required', 'ulid', 'different:cash_payment_id'],
            'card_amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'cash_payment_id.required' => 'Naqd to‘lov identifikatori majburiy.',
            'cash_payment_id.ulid' => 'Naqd to‘lov identifikatori noto‘g‘ri.',
            'cash_payment_id.different' => 'Naqd va karta to‘lov identifikatorlari har xil bo‘lishi kerak.',
            'cash_amount.required' => 'Naqd to‘lov summasini kiriting.',
            'cash_amount.integer' => 'Naqd to‘lov summasi butun son bo‘lishi kerak.',
            'cash_amount.min' => 'Naqd to‘lov summasi kamida 1 so‘m bo‘lishi kerak.',
            'card_payment_id.required' => 'Karta to‘lovi identifikatori majburiy.',
            'card_payment_id.ulid' => 'Karta to‘lovi identifikatori noto‘g‘ri.',
            'card_payment_id.different' => 'Naqd va karta to‘lov identifikatorlari har xil bo‘lishi kerak.',
            'card_amount.required' => 'Karta orqali to‘lov summasini kiriting.',
            'card_amount.integer' => 'Karta orqali to‘lov summasi butun son bo‘lishi kerak.',
            'card_amount.min' => 'Karta orqali to‘lov summasi kamida 1 so‘m bo‘lishi kerak.',
        ];
    }
}

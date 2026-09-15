<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class RemoveOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'ulid', 'distinct'],
            'items.*.order_item_id' => ['required', 'ulid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Ayiriladigan mahsulotlarni tanlang.',
            'items.min' => 'Kamida bitta mahsulotni ayiring.',
            'items.*.id.ulid' => 'Ayirish identifikatori noto‘g‘ri.',
            'items.*.order_item_id.ulid' => 'Buyurtma mahsuloti identifikatori noto‘g‘ri.',
            'items.*.quantity.min' => 'Ayirish miqdori kamida 1 bo‘lishi kerak.',
        ];
    }
}

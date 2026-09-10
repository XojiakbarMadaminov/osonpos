<?php

namespace App\Http\Requests\Api\Pos;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'ulid'],
            'type' => ['required', Rule::enum(OrderType::class)],
            'table_id' => ['nullable', 'integer', 'required_if:type,DINE_IN'],
            'customer_id' => ['nullable', 'ulid'],
            'customer' => ['nullable', 'array'],
            'customer.phone' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('type') === OrderType::Delivery->value && ! $this->filled('customer_id')),
                'string',
                'max:30',
            ],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'delivery' => ['nullable', 'array', 'required_if:type,DELIVERY'],
            'delivery.address' => ['nullable', 'string', 'max:2000', 'required_if:type,DELIVERY'],
            'delivery.fee' => ['nullable', 'integer', 'min:0', 'required_if:type,DELIVERY'],
            'delivery.note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

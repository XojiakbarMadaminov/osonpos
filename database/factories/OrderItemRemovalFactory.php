<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemRemovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'organization_id' => fn (array $attributes) => OrderItem::query()->findOrFail($attributes['order_item_id'])->organization_id,
            'store_id' => fn (array $attributes) => OrderItem::query()->findOrFail($attributes['order_item_id'])->store_id,
            'order_id' => fn (array $attributes) => OrderItem::query()->findOrFail($attributes['order_item_id'])->order_id,
            'quantity' => 1,
            'unit_price' => 10000,
            'unit_cost' => 0,
            'total' => 10000,
            'kitchen_print_required' => false,
            'kitchen_printed_at' => null,
            'created_by' => User::factory(),
        ];
    }
}

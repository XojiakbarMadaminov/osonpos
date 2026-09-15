<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(1000, 100000);

        return [
            'organization_id' => Organization::factory(),
            'store_id' => fn (array $attributes) => Store::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'order_id' => fn (array $attributes) => Order::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'store_id' => $attributes['store_id'],
            ]),
            'product_id' => null,
            'product_name' => fake()->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => fake()->numberBetween(0, $unitPrice),
            'total' => $quantity * $unitPrice,
            'note' => null,
            'kitchen_printed_at' => null,
            'created_by' => User::factory(),
        ];
    }
}

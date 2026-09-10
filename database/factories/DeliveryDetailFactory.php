<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Organization;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'store_id' => fn (array $attributes) => Store::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'order_id' => fn (array $attributes) => Order::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'store_id' => $attributes['store_id'],
            ]),
            'address' => fake()->address(),
            'delivery_fee' => fake()->numberBetween(0, 50000),
            'note' => fake()->optional()->sentence(),
        ];
    }
}

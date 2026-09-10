<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
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
            'device_id' => null,
            'method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1000, 100000),
            'created_by' => User::factory(),
        ];
    }
}

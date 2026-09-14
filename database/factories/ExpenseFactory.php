<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'store_id' => fn (array $attributes) => Store::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'type' => ExpenseType::Other,
            'amount' => fake()->numberBetween(10000, 1000000),
            'description' => fake()->sentence(),
            'incurred_on' => today(),
            'status' => ExpenseStatus::Active,
            'created_by' => User::factory(),
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->slug(2),
            'price' => fake()->numberBetween(100000, 1000000),
            'billing_period' => 'MONTHLY',
            'max_stores' => 1,
            'max_users' => 5,
            'is_active' => true,
        ];
    }
}

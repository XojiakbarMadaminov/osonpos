<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'store_id' => Store::factory()->for($organization),
            'name' => 'Table '.fake()->unique()->numberBetween(1, 999),
            'number' => (string) fake()->unique()->numberBetween(1, 999),
            'capacity' => fake()->optional()->numberBetween(1, 12),
            'is_active' => true,
        ];
    }
}

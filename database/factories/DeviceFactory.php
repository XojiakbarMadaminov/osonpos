<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'store_id' => Store::factory()->for($organization),
            'name' => 'POS '.fake()->unique()->numberBetween(1, 9999),
            'code' => fake()->unique()->bothify('POS-####'),
            'is_active' => true,
            'last_seen_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city().' Store',
            'address' => fake()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'timezone' => 'Asia/Tashkent',
            'is_active' => true,
        ];
    }
}

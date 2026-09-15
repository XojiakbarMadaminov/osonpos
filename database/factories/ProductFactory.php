<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category_id' => fn (array $attributes) => Category::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'store_id' => fn (array $attributes) => Category::query()->findOrFail($attributes['category_id'])->store_id,
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->numberBetween(5000, 100000),
            'cost_price' => fake()->numberBetween(0, 5000),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

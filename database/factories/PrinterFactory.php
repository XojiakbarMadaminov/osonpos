<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrinterFactory extends Factory
{
    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'store_id' => Store::factory()->for($organization),
            'device_id' => null,
            'name' => 'Printer '.fake()->unique()->numberBetween(1, 9999),
            'system_name' => null,
            'paper_width' => 80,
            'is_active' => true,
        ];
    }
}

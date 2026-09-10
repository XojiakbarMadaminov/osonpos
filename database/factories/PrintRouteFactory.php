<?php

namespace Database\Factories;

use App\Enums\PrintType;
use App\Models\Organization;
use App\Models\Printer;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrintRouteFactory extends Factory
{
    public function definition(): array
    {
        $organization = Organization::factory();
        $store = Store::factory()->for($organization);

        return [
            'organization_id' => $organization,
            'store_id' => $store,
            'print_type' => fake()->randomElement(PrintType::cases()),
            'printer_id' => Printer::factory()->for($organization)->for($store),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Device;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'store_id' => fn (array $attributes) => Store::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'device_id' => fn (array $attributes) => Device::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'store_id' => $attributes['store_id'],
            ]),
            'user_id' => User::factory(),
            'opening_cash' => 0,
            'closing_cash' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'status' => ShiftStatus::Open,
        ];
    }
}

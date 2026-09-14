<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'store_id' => fn (array $attributes) => Store::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'device_id' => null,
            'display_number' => '#'.fake()->unique()->numerify('####'),
            'business_date' => today(),
            'type' => OrderType::Takeaway,
            'table_id' => null,
            'customer_id' => null,
            'status' => OrderStatus::Open,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total' => 0,
            'note' => null,
            'created_by' => User::factory(),
            'closed_by' => null,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }
}

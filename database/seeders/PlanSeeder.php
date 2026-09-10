<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $features = collect([
            'pos' => 'Point of sale',
            'tables' => 'Table service',
            'delivery' => 'Delivery orders',
            'multi_store' => 'Multiple stores',
        ])->mapWithKeys(fn (string $name, string $code): array => [
            $code => Feature::query()->firstOrCreate(['code' => $code], ['name' => $name]),
        ]);

        $starter = Plan::query()->firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'price' => 200000,
                'billing_period' => 'MONTHLY',
                'max_stores' => 1,
                'max_users' => 5,
                'is_active' => true,
            ],
        );

        $starter->features()->sync($features->only(['pos', 'tables', 'delivery'])->pluck('id')->all());
    }
}

<?php

use Database\Seeders\DemoCatalogSeeder;

test('demo catalog contains ten categories with ten products each', function () {
    expect(DemoCatalogSeeder::CATALOG)->toHaveCount(10);

    foreach (DemoCatalogSeeder::CATALOG as $products) {
        expect($products)->toHaveCount(10);

        foreach ($products as $price) {
            expect($price)->toBeInt()->toBeGreaterThan(0);
        }
    }

    expect(collect(DemoCatalogSeeder::CATALOG)->flatten())->toHaveCount(100);
});

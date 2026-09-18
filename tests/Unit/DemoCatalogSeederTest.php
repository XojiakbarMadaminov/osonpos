<?php

use Database\Seeders\DemoCatalogSeeder;

test('demo catalog contains ten categories with ten products each', function () {
    expect(DemoCatalogSeeder::CATALOG)->toHaveCount(10);

    foreach (DemoCatalogSeeder::CATALOG as $products) {
        expect($products)->toHaveCount(10);

        foreach ($products as $productData) {
            expect($productData)->toBeArray()->toHaveKeys(['price', 'cost_price']);
            expect($productData['price'])->toBeInt()->toBeGreaterThan(0);
            expect($productData['cost_price'])->toBeInt()->toBeGreaterThan(0);
        }
    }

    expect(collect(DemoCatalogSeeder::CATALOG)->flatten(1))->toHaveCount(100);
});

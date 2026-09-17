<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array<string, array<string, int>>>
     */
    public const CATALOG = [
        'Burgerlar' => [
            'Klassik burger' => ['price' => 28_000, 'cost_price' => 20_000],
            'Chizburger' => ['price' => 32_000, 'cost_price' => 22_000],
            'Dabl burger' => ['price' => 42_000, 'cost_price' => 29_000],
            'Tovuqli burger' => ['price' => 30_000, 'cost_price' => 21_000],
            'Barbekyu burger' => ['price' => 36_000, 'cost_price' => 25_000],
            'Achchiq burger' => ['price' => 34_000, 'cost_price' => 24_000],
            'Mini burger' => ['price' => 20_000, 'cost_price' => 14_000],
            'Maxsus burger' => ['price' => 40_000, 'cost_price' => 28_000],
            'Qo‘ziqorinli burger' => ['price' => 37_000, 'cost_price' => 26_000],
            'Tuxumli burger' => ['price' => 35_000, 'cost_price' => 24_000],
        ],
        'Lavashlar' => [
            'Mol go‘shtli lavash' => ['price' => 32_000, 'cost_price' => 22_000],
            'Tovuqli lavash' => ['price' => 30_000, 'cost_price' => 21_000],
            'Pishloqli lavash' => ['price' => 35_000, 'cost_price' => 24_000],
            'Achchiq lavash' => ['price' => 33_000, 'cost_price' => 23_000],
            'Mini lavash' => ['price' => 24_000, 'cost_price' => 17_000],
            'Barbekyu lavash' => ['price' => 36_000, 'cost_price' => 25_000],
            'Tandir lavash' => ['price' => 34_000, 'cost_price' => 24_000],
            'Qo‘ziqorinli lavash' => ['price' => 37_000, 'cost_price' => 26_000],
            'Maxsus lavash' => ['price' => 39_000, 'cost_price' => 27_000],
            'Sabzavotli lavash' => ['price' => 26_000, 'cost_price' => 18_000],
        ],
        'Donarlar' => [
            'Mol go‘shtli donar' => ['price' => 30_000, 'cost_price' => 21_000],
            'Tovuqli donar' => ['price' => 28_000, 'cost_price' => 20_000],
            'Pishloqli donar' => ['price' => 33_000, 'cost_price' => 23_000],
            'Achchiq donar' => ['price' => 31_000, 'cost_price' => 22_000],
            'Mini donar' => ['price' => 22_000, 'cost_price' => 15_000],
            'Barbekyu donar' => ['price' => 34_000, 'cost_price' => 24_000],
            'Tandir donar' => ['price' => 32_000, 'cost_price' => 22_000],
            'Qo‘ziqorinli donar' => ['price' => 35_000, 'cost_price' => 24_000],
            'Maxsus donar' => ['price' => 37_000, 'cost_price' => 26_000],
            'Sabzavotli donar' => ['price' => 24_000, 'cost_price' => 17_000],
        ],
        'Hot-doglar' => [
            'Klassik hot-dog' => ['price' => 18_000, 'cost_price' => 13_000],
            'Pishloqli hot-dog' => ['price' => 21_000, 'cost_price' => 15_000],
            'Dabl hot-dog' => ['price' => 26_000, 'cost_price' => 18_000],
            'Achchiq hot-dog' => ['price' => 20_000, 'cost_price' => 14_000],
            'Mini hot-dog' => ['price' => 14_000, 'cost_price' => 10_000],
            'Barbekyu hot-dog' => ['price' => 22_000, 'cost_price' => 15_000],
            'Tovuqli hot-dog' => ['price' => 20_000, 'cost_price' => 14_000],
            'Maxsus hot-dog' => ['price' => 25_000, 'cost_price' => 17_000],
            'Kartoshkali hot-dog' => ['price' => 19_000, 'cost_price' => 13_000],
            'Tuxumli hot-dog' => ['price' => 23_000, 'cost_price' => 16_000],
        ],
        'Pitsalar' => [
            'Margarita pitsa' => ['price' => 55_000, 'cost_price' => 38_000],
            'Pepperoni pitsa' => ['price' => 68_000, 'cost_price' => 48_000],
            'Tovuqli pitsa' => ['price' => 65_000, 'cost_price' => 45_000],
            'Go‘shtli pitsa' => ['price' => 75_000, 'cost_price' => 52_000],
            'Pishloqli pitsa' => ['price' => 70_000, 'cost_price' => 49_000],
            'Qo‘ziqorinli pitsa' => ['price' => 66_000, 'cost_price' => 46_000],
            'Barbekyu pitsa' => ['price' => 72_000, 'cost_price' => 50_000],
            'Achchiq pitsa' => ['price' => 69_000, 'cost_price' => 48_000],
            'Aralash pitsa' => ['price' => 78_000, 'cost_price' => 55_000],
            'Sabzavotli pitsa' => ['price' => 58_000, 'cost_price' => 40_000],
        ],
        'Tovuqli taomlar' => [
            'Tovuq qanotlari 6 dona' => ['price' => 36_000, 'cost_price' => 25_000],
            'Tovuq qanotlari 12 dona' => ['price' => 65_000, 'cost_price' => 45_000],
            'Tovuq strips 3 dona' => ['price' => 28_000, 'cost_price' => 20_000],
            'Tovuq strips 6 dona' => ['price' => 49_000, 'cost_price' => 34_000],
            'Tovuq nuggets 6 dona' => ['price' => 25_000, 'cost_price' => 17_000],
            'Tovuq nuggets 12 dona' => ['price' => 45_000, 'cost_price' => 31_000],
            'Qarsildoq tovuq' => ['price' => 42_000, 'cost_price' => 29_000],
            'Achchiq tovuq' => ['price' => 44_000, 'cost_price' => 31_000],
            'Gril tovuq' => ['price' => 48_000, 'cost_price' => 34_000],
            'Tovuq assorti' => ['price' => 72_000, 'cost_price' => 50_000],
        ],
        'Garnirlar' => [
            'Fri kartoshka kichik' => ['price' => 12_000, 'cost_price' => 8_000],
            'Fri kartoshka katta' => ['price' => 18_000, 'cost_price' => 12_000],
            'Qishloqcha kartoshka' => ['price' => 20_000, 'cost_price' => 14_000],
            'Pishloqli fri' => ['price' => 23_000, 'cost_price' => 16_000],
            'Achchiq fri' => ['price' => 19_000, 'cost_price' => 13_000],
            'Piyoz halqalari' => ['price' => 22_000, 'cost_price' => 15_000],
            'Guruch' => ['price' => 14_000, 'cost_price' => 10_000],
            'Kartoshka pyuresi' => ['price' => 15_000, 'cost_price' => 10_000],
            'Gril sabzavotlar' => ['price' => 24_000, 'cost_price' => 17_000],
            'Non' => ['price' => 5_000, 'cost_price' => 3_000],
        ],
        'Salatlar' => [
            'Sezar salati' => ['price' => 32_000, 'cost_price' => 22_000],
            'Achchiq-chuchuk salati' => ['price' => 18_000, 'cost_price' => 12_000],
            'Bahor salati' => ['price' => 20_000, 'cost_price' => 14_000],
            'Grek salati' => ['price' => 28_000, 'cost_price' => 20_000],
            'Olivye salati' => ['price' => 24_000, 'cost_price' => 17_000],
            'Tovuqli salat' => ['price' => 30_000, 'cost_price' => 21_000],
            'Karam salati' => ['price' => 16_000, 'cost_price' => 11_000],
            'Bodring salati' => ['price' => 17_000, 'cost_price' => 12_000],
            'Pomidor salati' => ['price' => 18_000, 'cost_price' => 12_000],
            'Maxsus salat' => ['price' => 34_000, 'cost_price' => 24_000],
        ],
        'Ichimliklar' => [
            'Coca-Cola 0.5 l' => ['price' => 10_000, 'cost_price' => 7_000],
            'Coca-Cola 1 l' => ['price' => 16_000, 'cost_price' => 11_000],
            'Fanta 0.5 l' => ['price' => 10_000, 'cost_price' => 7_000],
            'Sprite 0.5 l' => ['price' => 10_000, 'cost_price' => 7_000],
            'Suv 0.5 l' => ['price' => 5_000, 'cost_price' => 3_000],
            'Suv 1 l' => ['price' => 8_000, 'cost_price' => 5_000],
            'Ayran' => ['price' => 9_000, 'cost_price' => 6_000],
            'Ko‘k choy' => ['price' => 6_000, 'cost_price' => 4_000],
            'Qora choy' => ['price' => 6_000, 'cost_price' => 4_000],
            'Limonli choy' => ['price' => 12_000, 'cost_price' => 8_000],
        ],
        'Desertlar' => [
            'Chizkeyk' => ['price' => 25_000, 'cost_price' => 17_000],
            'Shokoladli tort' => ['price' => 24_000, 'cost_price' => 17_000],
            'Asalli tort' => ['price' => 23_000, 'cost_price' => 16_000],
            'Tiramisu' => ['price' => 28_000, 'cost_price' => 20_000],
            'Brauni' => ['price' => 22_000, 'cost_price' => 15_000],
            'Donat' => ['price' => 14_000, 'cost_price' => 10_000],
            'Muzqaymoq' => ['price' => 16_000, 'cost_price' => 11_000],
            'Mevali desert' => ['price' => 20_000, 'cost_price' => 14_000],
            'Vafli' => ['price' => 18_000, 'cost_price' => 12_000],
            'Pechenye' => ['price' => 10_000, 'cost_price' => 7_000],
        ],
    ];

    public function run(): void
    {
        if (! Organization::query()->exists()) {
            $this->command?->warn('Tashkilot topilmadi. Avval tashkilot yarating.');

            return;
        }

        Organization::query()->with('stores')->each(function (Organization $organization): void {
            $organization->stores->each(function ($store) use ($organization): void {
                DB::transaction(function () use ($organization, $store): void {
                    $categorySortOrder = 0;

                    foreach (self::CATALOG as $categoryName => $products) {
                        $categorySortOrder++;
                        $category = $store->categories()->updateOrCreate(
                            ['name' => $categoryName],
                            [
                                'organization_id' => $organization->getKey(),
                                'sort_order' => $categorySortOrder,
                                'is_active' => true,
                            ],
                        );

                        $productSortOrder = 0;

                        foreach ($products as $productName => $productData) {
                            $productSortOrder++;
                            $store->products()->updateOrCreate(
                                [
                                    'category_id' => $category->getKey(),
                                    'name' => $productName,
                                ],
                                [
                                    'organization_id' => $organization->getKey(),
                                    'price' => $productData['price'],
                                    'cost_price' => $productData['cost_price'],
                                    'sort_order' => $productSortOrder,
                                    'is_active' => true,
                                ],
                            );
                        }
                    }
                });
            });

            $this->command?->info("{$organization->name}: har bir filial uchun 10 ta kategoriya va 100 ta mahsulot tayyor.");
        });
    }
}

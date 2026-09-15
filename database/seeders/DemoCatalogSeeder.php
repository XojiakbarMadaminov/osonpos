<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array<string, int>>
     */
    public const CATALOG = [
        'Burgerlar' => [
            'Klassik burger' => 28_000,
            'Chizburger' => 32_000,
            'Dabl burger' => 42_000,
            'Tovuqli burger' => 30_000,
            'Barbekyu burger' => 36_000,
            'Achchiq burger' => 34_000,
            'Mini burger' => 20_000,
            'Maxsus burger' => 40_000,
            'Qo‘ziqorinli burger' => 37_000,
            'Tuxumli burger' => 35_000,
        ],
        'Lavashlar' => [
            'Mol go‘shtli lavash' => 32_000,
            'Tovuqli lavash' => 30_000,
            'Pishloqli lavash' => 35_000,
            'Achchiq lavash' => 33_000,
            'Mini lavash' => 24_000,
            'Barbekyu lavash' => 36_000,
            'Tandir lavash' => 34_000,
            'Qo‘ziqorinli lavash' => 37_000,
            'Maxsus lavash' => 39_000,
            'Sabzavotli lavash' => 26_000,
        ],
        'Donarlar' => [
            'Mol go‘shtli donar' => 30_000,
            'Tovuqli donar' => 28_000,
            'Pishloqli donar' => 33_000,
            'Achchiq donar' => 31_000,
            'Mini donar' => 22_000,
            'Barbekyu donar' => 34_000,
            'Tandir donar' => 32_000,
            'Qo‘ziqorinli donar' => 35_000,
            'Maxsus donar' => 37_000,
            'Sabzavotli donar' => 24_000,
        ],
        'Hot-doglar' => [
            'Klassik hot-dog' => 18_000,
            'Pishloqli hot-dog' => 21_000,
            'Dabl hot-dog' => 26_000,
            'Achchiq hot-dog' => 20_000,
            'Mini hot-dog' => 14_000,
            'Barbekyu hot-dog' => 22_000,
            'Tovuqli hot-dog' => 20_000,
            'Maxsus hot-dog' => 25_000,
            'Kartoshkali hot-dog' => 19_000,
            'Tuxumli hot-dog' => 23_000,
        ],
        'Pitsalar' => [
            'Margarita pitsa' => 55_000,
            'Pepperoni pitsa' => 68_000,
            'Tovuqli pitsa' => 65_000,
            'Go‘shtli pitsa' => 75_000,
            'Pishloqli pitsa' => 70_000,
            'Qo‘ziqorinli pitsa' => 66_000,
            'Barbekyu pitsa' => 72_000,
            'Achchiq pitsa' => 69_000,
            'Aralash pitsa' => 78_000,
            'Sabzavotli pitsa' => 58_000,
        ],
        'Tovuqli taomlar' => [
            'Tovuq qanotlari 6 dona' => 36_000,
            'Tovuq qanotlari 12 dona' => 65_000,
            'Tovuq strips 3 dona' => 28_000,
            'Tovuq strips 6 dona' => 49_000,
            'Tovuq nuggets 6 dona' => 25_000,
            'Tovuq nuggets 12 dona' => 45_000,
            'Qarsildoq tovuq' => 42_000,
            'Achchiq tovuq' => 44_000,
            'Gril tovuq' => 48_000,
            'Tovuq assorti' => 72_000,
        ],
        'Garnirlar' => [
            'Fri kartoshka kichik' => 12_000,
            'Fri kartoshka katta' => 18_000,
            'Qishloqcha kartoshka' => 20_000,
            'Pishloqli fri' => 23_000,
            'Achchiq fri' => 19_000,
            'Piyoz halqalari' => 22_000,
            'Guruch' => 14_000,
            'Kartoshka pyuresi' => 15_000,
            'Gril sabzavotlar' => 24_000,
            'Non' => 5_000,
        ],
        'Salatlar' => [
            'Sezar salati' => 32_000,
            'Achchiq-chuchuk salati' => 18_000,
            'Bahor salati' => 20_000,
            'Grek salati' => 28_000,
            'Olivye salati' => 24_000,
            'Tovuqli salat' => 30_000,
            'Karam salati' => 16_000,
            'Bodring salati' => 17_000,
            'Pomidor salati' => 18_000,
            'Maxsus salat' => 34_000,
        ],
        'Ichimliklar' => [
            'Coca-Cola 0.5 l' => 10_000,
            'Coca-Cola 1 l' => 16_000,
            'Fanta 0.5 l' => 10_000,
            'Sprite 0.5 l' => 10_000,
            'Suv 0.5 l' => 5_000,
            'Suv 1 l' => 8_000,
            'Ayran' => 9_000,
            'Ko‘k choy' => 6_000,
            'Qora choy' => 6_000,
            'Limonli choy' => 12_000,
        ],
        'Desertlar' => [
            'Chizkeyk' => 25_000,
            'Shokoladli tort' => 24_000,
            'Asalli tort' => 23_000,
            'Tiramisu' => 28_000,
            'Brauni' => 22_000,
            'Donat' => 14_000,
            'Muzqaymoq' => 16_000,
            'Mevali desert' => 20_000,
            'Vafli' => 18_000,
            'Pechenye' => 10_000,
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

                        foreach ($products as $productName => $price) {
                            $productSortOrder++;
                            $store->products()->updateOrCreate(
                                [
                                    'category_id' => $category->getKey(),
                                    'name' => $productName,
                                ],
                                [
                                    'organization_id' => $organization->getKey(),
                                    'price' => $price,
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });

        $this->copyExistingCatalogsToEveryStore();

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable(false)->change();
            $table->dropIndex('categories_organization_id_is_active_sort_order_index');
            $table->index(['organization_id', 'store_id', 'is_active', 'sort_order']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable(false)->change();
            $table->dropIndex('products_organization_id_is_active_sort_order_index');
            $table->dropIndex('products_organization_id_category_id_is_active_index');
            $table->index(['organization_id', 'store_id', 'is_active', 'sort_order']);
            $table->index(['organization_id', 'store_id', 'category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'store_id', 'is_active', 'sort_order']);
            $table->dropIndex(['organization_id', 'store_id', 'category_id', 'is_active']);
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
            $table->index(['organization_id', 'is_active', 'sort_order']);
            $table->index(['organization_id', 'category_id', 'is_active']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'store_id', 'is_active', 'sort_order']);
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
            $table->index(['organization_id', 'is_active', 'sort_order']);
        });
    }

    private function copyExistingCatalogsToEveryStore(): void
    {
        DB::table('categories')
            ->select('organization_id')
            ->distinct()
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->each(function (int $organizationId): void {
                $storeIds = DB::table('stores')
                    ->where('organization_id', $organizationId)
                    ->orderBy('id')
                    ->pluck('id');

                if ($storeIds->isEmpty()) {
                    throw new RuntimeException("{$organizationId}-tashkilot katalogini biriktirish uchun filial topilmadi.");
                }

                $categories = DB::table('categories')
                    ->where('organization_id', $organizationId)
                    ->orderBy('id')
                    ->get();
                $products = DB::table('products')
                    ->where('organization_id', $organizationId)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('category_id');

                $primaryStoreId = (int) $storeIds->shift();

                DB::table('categories')->where('organization_id', $organizationId)->update(['store_id' => $primaryStoreId]);
                DB::table('products')->where('organization_id', $organizationId)->update(['store_id' => $primaryStoreId]);

                foreach ($storeIds as $storeId) {
                    foreach ($categories as $category) {
                        $newCategoryId = DB::table('categories')->insertGetId([
                            'organization_id' => $organizationId,
                            'store_id' => $storeId,
                            'name' => $category->name,
                            'sort_order' => $category->sort_order,
                            'is_active' => $category->is_active,
                            'created_at' => $category->created_at,
                            'updated_at' => $category->updated_at,
                        ]);

                        foreach ($products->get($category->id, collect()) as $product) {
                            DB::table('products')->insert([
                                'organization_id' => $organizationId,
                                'store_id' => $storeId,
                                'category_id' => $newCategoryId,
                                'name' => $product->name,
                                'price' => $product->price,
                                'is_active' => $product->is_active,
                                'sort_order' => $product->sort_order,
                                'created_at' => $product->created_at,
                                'updated_at' => $product->updated_at,
                            ]);
                        }
                    }
                }
            });
    }
};

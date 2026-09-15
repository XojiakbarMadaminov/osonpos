<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('cost_price')->default(0)->after('price');
        });
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_cost_price_non_negative CHECK (cost_price >= 0)');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_cost')->default(0)->after('unit_price');
        });
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_cost_non_negative CHECK (unit_cost >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT order_items_unit_cost_non_negative');
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });

        DB::statement('ALTER TABLE products DROP CONSTRAINT products_cost_price_non_negative');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('cost_price');
        });
    }
};

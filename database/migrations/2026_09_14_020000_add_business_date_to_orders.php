<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->date('business_date')->nullable()->after('display_number');
        });

        DB::table('orders')
            ->join('stores', 'stores.id', '=', 'orders.store_id')
            ->select(['orders.id', 'orders.opened_at', 'stores.timezone'])
            ->orderBy('orders.id')
            ->get()
            ->each(function (object $order): void {
                DB::table('orders')->where('id', $order->id)->update([
                    'business_date' => CarbonImmutable::parse($order->opened_at)
                        ->setTimezone($order->timezone)
                        ->toDateString(),
                ]);
            });

        Schema::table('orders', function (Blueprint $table): void {
            $table->date('business_date')->nullable(false)->change();
            $table->dropUnique(['store_id', 'display_number']);
            $table->unique(
                ['store_id', 'business_date', 'display_number'],
                'orders_store_business_display_unique',
            );
            $table->index(
                ['store_id', 'business_date', 'status'],
                'orders_store_business_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_store_business_display_unique');
            $table->dropIndex('orders_store_business_status_index');
        });

        $orders = DB::table('orders')
            ->select(['id', 'store_id'])
            ->orderBy('store_id')
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'display_number' => '#ROLLBACK-'.$order->id,
            ]);
        }

        $sequences = [];
        foreach ($orders as $order) {
            $sequence = ($sequences[$order->store_id] ?? 0) + 1;
            $sequences[$order->store_id] = $sequence;

            DB::table('orders')->where('id', $order->id)->update([
                'display_number' => '#'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            ]);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('business_date');
            $table->unique(['store_id', 'display_number']);
        });
    }
};

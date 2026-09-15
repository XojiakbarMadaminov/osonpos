<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('customer_phone')->nullable()->after('customer_name');
        });

        DB::table('orders')
            ->whereNotNull('customer_id')
            ->orderBy('id')
            ->eachById(function (object $order): void {
                $customer = DB::table('customers')->where('id', $order->customer_id)->first(['name', 'phone']);

                if ($customer) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'customer_name' => $customer->name,
                        'customer_phone' => $customer->phone,
                    ]);
                }
            }, column: 'id');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_phone']);
        });
    }
};

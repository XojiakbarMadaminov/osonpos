<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('discount_type')->nullable()->after('delivery_fee');
            $table->unsignedBigInteger('discount_value')->nullable()->after('discount_type');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });
    }
};

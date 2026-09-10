<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['organization_id', 'opened_at'], 'orders_org_opened_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'payments_org_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_org_created_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_org_opened_at_index');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_removals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('total');
            $table->boolean('kitchen_print_required')->default(false);
            $table->timestampTz('kitchen_printed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['organization_id', 'store_id', 'order_id']);
            $table->index(['order_id', 'kitchen_print_required', 'kitchen_printed_at']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_removals');
    }
};

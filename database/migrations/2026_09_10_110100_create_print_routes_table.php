<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('print_type');
            $table->foreignId('printer_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['store_id', 'print_type']);
            $table->index(['organization_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_routes');
    }
};

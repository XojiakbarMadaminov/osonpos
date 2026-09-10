<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('system_name')->nullable();
            $table->unsignedSmallInteger('paper_width')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['organization_id', 'store_id', 'is_active']);
            $table->unique(['device_id', 'system_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};

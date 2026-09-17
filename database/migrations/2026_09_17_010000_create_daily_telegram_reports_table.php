<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_telegram_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['store_id', 'business_date']);
            $table->index(['organization_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_telegram_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('opening_cash');
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->string('status');
            $table->timestampsTz();

            $table->index(['organization_id', 'store_id', 'status']);
            $table->index(['store_id', 'opened_at']);
        });

        DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_device ON shifts (device_id) WHERE status = 'OPEN'");
        DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_user ON shifts (organization_id, user_id) WHERE status = 'OPEN'");
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};

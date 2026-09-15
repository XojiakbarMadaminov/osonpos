<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('devices')->update([
            'activation_code_hash' => null,
        ]);

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('activation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->timestampTz('activation_expires_at')->nullable();
        });

        // Cleared one-time activation codes cannot be restored safely.
    }
};

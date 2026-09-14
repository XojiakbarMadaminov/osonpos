<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('activation_code_hash', 64)->nullable()->unique();
            $table->timestampTz('activation_expires_at')->nullable();
            $table->string('credential_hash', 64)->nullable();
            $table->timestampTz('activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropUnique(['activation_code_hash']);
            $table->dropColumn([
                'activation_code_hash',
                'activation_expires_at',
                'credential_hash',
                'activated_at',
            ]);
        });
    }
};

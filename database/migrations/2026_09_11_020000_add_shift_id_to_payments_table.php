<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignUlid('shift_id')
                ->nullable()
                ->after('device_id')
                ->constrained('shifts')
                ->restrictOnDelete();
            $table->index(['shift_id', 'method']);
        });

        DB::statement(<<<'SQL'
            UPDATE payments AS payment
            SET shift_id = (
                SELECT shifts.id
                FROM shifts
                WHERE shifts.organization_id = payment.organization_id
                  AND shifts.store_id = payment.store_id
                  AND shifts.device_id = payment.device_id
                  AND shifts.user_id = payment.created_by
                  AND shifts.opened_at <= payment.created_at
                  AND (shifts.closed_at IS NULL OR shifts.closed_at >= payment.created_at)
                ORDER BY shifts.opened_at DESC
                LIMIT 1
            )
            WHERE payment.shift_id IS NULL
            SQL);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['shift_id']);
            $table->dropIndex(['shift_id', 'method']);
            $table->dropColumn('shift_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('amount');
            $table->text('description');
            $table->date('incurred_on');
            $table->string('status')->default('ACTIVE');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'store_id', 'incurred_on']);
            $table->index(['organization_id', 'status', 'incurred_on']);
            $table->index(['organization_id', 'type', 'incurred_on']);
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_positive CHECK (amount > 0)');
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_type_valid CHECK (type IN ('PRODUCT_COST', 'RENT', 'SALARY', 'OTHER'))");
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_status_valid CHECK (status IN ('ACTIVE', 'CANCELLED'))");

        $now = now();
        foreach (['expenses.view', 'expenses.manage'] as $permissionName) {
            $permissionId = DB::table('permissions')->where([
                'name' => $permissionName,
                'guard_name' => 'web',
            ])->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('roles')
                ->where('name', 'Owner')
                ->where('guard_name', 'web')
                ->pluck('id')
                ->each(fn (int $roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        DB::table('permissions')->whereIn('name', ['expenses.view', 'expenses.manage'])->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_features', function (Blueprint $table) {
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->primary(['subscription_id', 'feature_id']);
        });

        Schema::create('telegram_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('group_chat_id');
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('telegram_notified_at')->nullable()->after('updated_at');
        });

        $now = now();
        $featureId = DB::table('features')->where('code', 'telegram_payment_notifications')->value('id');
        if (! $featureId) {
            $featureId = DB::table('features')->insertGetId([
                'code' => 'telegram_payment_notifications',
                'name' => 'Telegram orqali to‘lov xabarlari',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'telegram_settings.manage')
            ->where('guard_name', 'web')
            ->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'telegram_settings.manage',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('role_has_permissions')->insertOrIgnoreUsing(
            ['permission_id', 'role_id'],
            DB::table('roles')
                ->selectRaw('? as permission_id, id', [$permissionId])
                ->whereIn('name', ['Owner', 'Manager']),
        );

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'telegram_settings.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('features')->where('code', 'telegram_payment_notifications')->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('telegram_notified_at');
        });

        Schema::dropIfExists('telegram_settings');
        Schema::dropIfExists('subscription_features');

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};

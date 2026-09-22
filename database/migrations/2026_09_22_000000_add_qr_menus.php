<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->char('menu_token', 26)->nullable()->unique();
            $table->boolean('is_qr_menu_enabled')->default(false);
        });

        DB::table('stores')->select('id')->orderBy('id')->chunkById(100, function ($stores): void {
            foreach ($stores as $store) {
                DB::table('stores')->where('id', $store->id)->update(['menu_token' => (string) Str::ulid()]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
        });

        DB::table('features')->insertOrIgnore([
            'code' => 'qr_menu',
            'name' => 'QR orqali onlayn menyu',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('name', 'qr_menu.manage')->where('guard_name', 'web')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'qr_menu.manage',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_has_permissions')->insertOrIgnoreUsing(
            ['permission_id', 'role_id'],
            DB::table('roles')->selectRaw('? as permission_id, id', [$permissionId])->whereIn('name', ['Owner', 'Manager']),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'qr_menu.manage')->where('guard_name', 'web')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('features')->where('code', 'qr_menu')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['description', 'image_path']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['menu_token', 'is_qr_menu_enabled']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

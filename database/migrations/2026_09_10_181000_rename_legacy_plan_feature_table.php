<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_feature') && ! Schema::hasTable('plan_features')) {
            Schema::rename('plan_feature', 'plan_features');
        }
    }

    public function down(): void
    {
        // The canonical foundation migration already creates `plan_features`.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestamps();

            $table->index(['organization_id', 'status', 'ends_at']);
        });

        DB::statement("CREATE UNIQUE INDEX subscriptions_one_current_per_organization ON subscriptions (organization_id) WHERE status IN ('TRIAL', 'ACTIVE')");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'actor_role')) {
                $table->string('actor_role', 50)->nullable()->after('user_id')->comment('Actor role');
            }
            if (!Schema::hasColumn('activity_logs', 'target_type')) {
                $table->string('target_type', 100)->nullable()->after('actor_role')->comment('Target model type');
            }
            if (!Schema::hasColumn('activity_logs', 'target_id')) {
                $table->unsignedBigInteger('target_id')->nullable()->after('target_type')->comment('Target model ID');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_role', 'target_type', 'target_id']);
        });
    }
};



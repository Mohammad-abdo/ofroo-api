<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'moderated_by_admin_id')) {
                $table->foreignId('moderated_by_admin_id')->nullable()->after('rating')->constrained('users')->onDelete('set null')->comment('Admin who moderated');
            }
            if (!Schema::hasColumn('reviews', 'moderation_action')) {
                $table->enum('moderation_action', ['none', 'deleted', 'hidden'])->default('none')->after('moderated_by_admin_id')->comment('Moderation action');
            }
            if (!Schema::hasColumn('reviews', 'moderation_reason')) {
                $table->text('moderation_reason')->nullable()->after('moderation_action')->comment('Moderation reason');
            }
            if (!Schema::hasColumn('reviews', 'moderation_at')) {
                $table->timestamp('moderation_at')->nullable()->after('moderation_reason')->comment('Moderation timestamp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Drop foreign key first if it exists
            if (Schema::hasColumn('reviews', 'moderated_by_admin_id')) {
                $table->dropForeign(['moderated_by_admin_id']);
            }
        });
        
        Schema::table('reviews', function (Blueprint $table) {
            // Now drop columns
            if (Schema::hasColumn('reviews', 'moderated_by_admin_id')) {
                $table->dropColumn('moderated_by_admin_id');
            }
            if (Schema::hasColumn('reviews', 'moderation_action')) {
                $table->dropColumn('moderation_action');
            }
            if (Schema::hasColumn('reviews', 'moderation_reason')) {
                $table->dropColumn('moderation_reason');
            }
            if (Schema::hasColumn('reviews', 'moderation_at')) {
                $table->dropColumn('moderation_at');
            }
        });
    }
};



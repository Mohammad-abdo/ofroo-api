<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalise `app_policies` so it can store multiple kinds of static-page
 * sections (privacy policy, about, support, etc.) instead of just privacy.
 *
 * The `type` column lets the admin dashboard render a separate CRUD tab per
 * kind, while the mobile app filters by type at read time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_policies') && ! Schema::hasColumn('app_policies', 'type')) {
            Schema::table('app_policies', function (Blueprint $table) {
                $table->string('type', 32)->default('privacy')->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_policies') && Schema::hasColumn('app_policies', 'type')) {
            Schema::table('app_policies', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
};

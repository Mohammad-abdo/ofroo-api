<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Add localization and permissions columns if they don't exist
            if (!Schema::hasColumn('roles', 'name_ar')) {
                $table->string('name_ar', 50)->nullable()->after('name')->comment('اسم الدور بالعربية');
            }
            if (!Schema::hasColumn('roles', 'name_en')) {
                $table->string('name_en', 50)->nullable()->after('name_ar')->comment('Role name in English');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->string('description', 255)->nullable()->after('guard_name')->comment('Role description');
            }
            if (!Schema::hasColumn('roles', 'description_ar')) {
                $table->string('description_ar', 255)->nullable()->after('description')->comment('وصف الدور بالعربية');
            }
            if (!Schema::hasColumn('roles', 'description_en')) {
                $table->string('description_en', 255)->nullable()->after('description_ar')->comment('Role description in English');
            }
            if (!Schema::hasColumn('roles', 'permissions')) {
                $table->json('permissions')->nullable()->after('description_en')->comment('JSON permissions array');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('roles', 'name_ar')) {
                $table->dropColumn('name_ar');
            }
            if (Schema::hasColumn('roles', 'name_en')) {
                $table->dropColumn('name_en');
            }
            if (Schema::hasColumn('roles', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('roles', 'description_ar')) {
                $table->dropColumn('description_ar');
            }
            if (Schema::hasColumn('roles', 'description_en')) {
                $table->dropColumn('description_en');
            }
            if (Schema::hasColumn('roles', 'permissions')) {
                $table->dropColumn('permissions');
            }
        });
    }
};

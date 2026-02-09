<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Note: This table might conflict with Spatie Permission package
        // If Spatie Permission is being used, this migration should be skipped or table renamed
        // Drop if exists to avoid conflicts
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop foreign keys from related tables first
        $tableNames = config('permission.table_names', []);
        if (!empty($tableNames)) {
            if (Schema::hasTable($tableNames['role_has_permissions'] ?? 'role_has_permissions')) {
                try {
                    Schema::table($tableNames['role_has_permissions'] ?? 'role_has_permissions', function (Blueprint $table) {
                        $table->dropForeign(['permission_id']);
                    });
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
            }
            if (Schema::hasTable($tableNames['model_has_permissions'] ?? 'model_has_permissions')) {
                try {
                    Schema::table($tableNames['model_has_permissions'] ?? 'model_has_permissions', function (Blueprint $table) {
                        $table->dropForeign(['permission_id']);
                    });
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
            }
        }
        
        Schema::dropIfExists('permissions');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Permission name (e.g., users.view)');
            $table->string('name_ar', 100)->nullable()->comment('اسم الصلاحية بالعربية');
            $table->string('name_en', 100)->nullable()->comment('Permission name in English');
            $table->string('group', 50)->comment('Permission group (users, merchants, orders, etc)');
            $table->string('group_ar', 50)->nullable()->comment('مجموعة الصلاحية بالعربية');
            $table->string('group_en', 50)->nullable()->comment('Permission group in English');
            $table->text('description')->nullable()->comment('Permission description');
            $table->text('description_ar')->nullable()->comment('وصف الصلاحية بالعربية');
            $table->text('description_en')->nullable()->comment('Permission description in English');
            $table->timestamps();
            
            $table->index('group');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop table if exists
        Schema::dropIfExists('permissions');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};

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
        // Check if table exists before trying to alter it
        if (!Schema::hasTable('store_locations')) {
            return; // Table doesn't exist yet, skip this migration
        }
        
        // Check if columns don't exist before adding them
        if (!Schema::hasColumn('store_locations', 'name_ar')) {
            Schema::table('store_locations', function (Blueprint $table) {
                $table->string('name_ar', 255)->nullable()->after('merchant_id')->comment('اسم الفرع بالعربية');
            });
        }
        
        if (!Schema::hasColumn('store_locations', 'name_en')) {
            Schema::table('store_locations', function (Blueprint $table) {
                $table->string('name_en', 255)->nullable()->after('name_ar')->comment('Branch name in English');
            });
        }
        
        if (!Schema::hasColumn('store_locations', 'phone')) {
            Schema::table('store_locations', function (Blueprint $table) {
                $table->string('phone', 50)->nullable()->after('name_en')->comment('Branch phone number');
            });
        }
        
        if (!Schema::hasColumn('store_locations', 'is_active')) {
            Schema::table('store_locations', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('phone')->comment('Branch active status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if table exists before trying to alter it
        if (!Schema::hasTable('store_locations')) {
            return; // Table doesn't exist, skip this migration
        }
        
        Schema::table('store_locations', function (Blueprint $table) {
            if (Schema::hasColumn('store_locations', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('store_locations', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('store_locations', 'name_en')) {
                $table->dropColumn('name_en');
            }
            if (Schema::hasColumn('store_locations', 'name_ar')) {
                $table->dropColumn('name_ar');
            }
        });
    }
};

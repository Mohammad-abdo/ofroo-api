<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill bilingual titles from legacy `title` where Arabic/English are missing.
 * Does not drop `title` — only normalizes data and sets default NULL for new rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        DB::table('coupons')
            ->whereNotNull('title')
            ->where(function ($q) {
                $q->whereNull('title_ar')->orWhere('title_ar', '');
            })
            ->update(['title_ar' => DB::raw('title')]);

        DB::table('coupons')
            ->whereNotNull('title')
            ->where(function ($q) {
                $q->whereNull('title_en')->orWhere('title_en', '');
            })
            ->update(['title_en' => DB::raw('title')]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE coupons MODIFY title VARCHAR(255) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE coupons MODIFY title VARCHAR(255) NOT NULL');
        }
    }
};

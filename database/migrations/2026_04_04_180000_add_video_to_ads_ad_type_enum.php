<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * API already allows ad_type=video; MySQL ENUM must include it or updates truncate / fail.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ads MODIFY COLUMN ad_type ENUM('banner', 'popup', 'sidebar', 'inline', 'video') NOT NULL DEFAULT 'banner'");
    }

    public function down(): void
    {
        DB::table('ads')->where('ad_type', 'video')->update(['ad_type' => 'banner']);
        DB::statement("ALTER TABLE ads MODIFY COLUMN ad_type ENUM('banner', 'popup', 'sidebar', 'inline') NOT NULL DEFAULT 'banner'");
    }
};

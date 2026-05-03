<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Video ads may have only video_url; poster image is optional.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `ads` MODIFY `image_url` VARCHAR(500) NULL COMMENT \'Ad image URL (optional for video ads)\'');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('UPDATE `ads` SET `image_url` = \'\' WHERE `image_url` IS NULL');
        DB::statement('ALTER TABLE `ads` MODIFY `image_url` VARCHAR(500) NOT NULL COMMENT \'Ad image URL\'');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * otp_code stores Hash::make() output (~60 chars). The column was VARCHAR(10), causing SQL 1406.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'otp_code')) {
            return;
        }

        DB::statement('ALTER TABLE `users` MODIFY `otp_code` VARCHAR(255) NULL COMMENT \'Hashed OTP for verification\'');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'otp_code')) {
            return;
        }

        DB::statement('ALTER TABLE `users` MODIFY `otp_code` VARCHAR(10) NULL COMMENT \'OTP code for verification\'');
    }
};

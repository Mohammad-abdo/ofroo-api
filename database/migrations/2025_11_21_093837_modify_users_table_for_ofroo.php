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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->unique()->nullable()->after('email')->comment('Phone number');
            $table->string('country', 100)->nullable()->after('phone')->comment('User country');
            $table->string('city', 100)->nullable()->after('country')->comment('User city');
            $table->enum('language', ['ar', 'en'])->default('ar')->after('phone')->comment('User preferred language');
            $table->foreignId('role_id')->nullable()->after('language')->constrained('roles')->onDelete('set null')->comment('User role');
            $table->string('otp_code', 10)->nullable()->after('email_verified_at')->comment('OTP code for verification');
            $table->dateTime('otp_expires_at')->nullable()->after('otp_code')->comment('OTP expiration time');
            $table->decimal('last_location_lat', 10, 7)->nullable()->after('otp_expires_at')->comment('Last known latitude');
            $table->decimal('last_location_lng', 10, 7)->nullable()->after('last_location_lat')->comment('Last known longitude');
        });

        // Add index for geo queries
        Schema::table('users', function (Blueprint $table) {
            $table->index(['last_location_lat', 'last_location_lng'], 'users_location_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_location_idx');
            $table->dropForeign(['role_id']);
            $table->dropColumn(['phone', 'language', 'role_id', 'otp_code', 'otp_expires_at', 'last_location_lat', 'last_location_lng']);
        });
    }
};

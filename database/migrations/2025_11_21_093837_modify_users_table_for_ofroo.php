<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Merges former standalone migrations (chronological order preserves column placement):
     * 2024_12_20_add_user_settings_fields,
     * original modify_users,
     * 2025_11_22_add_is_blocked_and_country_to_users_table,
     * 2025_11_22_add_city_to_users_table,
     * 2026_02_11_add_gender_city_governorate_to_users_table (FKs to cities/governorates added later if needed).
     */
    public function up(): void
    {
        // Was: 2024_12_20_000001_add_user_settings_fields
        Schema::table('users', function (Blueprint $table) {
            $afterColumn = 'email_verified_at';
            if (Schema::hasColumn('users', 'city')) {
                $afterColumn = 'city';
            } elseif (Schema::hasColumn('users', 'country')) {
                $afterColumn = 'country';
            }

            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after($afterColumn);
            }
            if (! Schema::hasColumn('users', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true)->after('avatar');
            }
            if (! Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true)->after('notifications_enabled');
            }
            if (! Schema::hasColumn('users', 'push_notifications')) {
                $table->boolean('push_notifications')->default(true)->after('email_notifications');
            }
        });

        // Original OFROO user fields
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->unique()->nullable()->after('email')->comment('Phone number');
            $table->string('country', 100)->nullable()->after('phone')->comment('User country');
            $table->string('city', 100)->nullable()->after('country')->comment('User city');
            $table->enum('language', ['ar', 'en'])->default('ar')->after('phone')->comment('User preferred language');
            $table->foreignId('role_id')->nullable()->after('language')->constrained('roles')->onDelete('set null')->comment('User role');
            $table->string('otp_code', 255)->nullable()->after('email_verified_at')->comment('Hashed OTP for verification');
            $table->dateTime('otp_expires_at')->nullable()->after('otp_code')->comment('OTP expiration time');
            $table->decimal('last_location_lat', 10, 7)->nullable()->after('otp_expires_at')->comment('Last known latitude');
            $table->decimal('last_location_lng', 10, 7)->nullable()->after('last_location_lat')->comment('Last known longitude');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['last_location_lat', 'last_location_lng'], 'users_location_idx');
        });

        // Was: 2025_11_22_143827_add_is_blocked_and_country_to_users_table
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country', 100)->nullable();
            }
        });

        // Was: 2025_11_22_150026_add_city_to_users_table
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city', 100)->nullable()->after('country');
            }
        });

        // Was: 2026_02_11_000001_add_gender_city_governorate_to_users_table
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('phone');
            $table->unsignedBigInteger('city_id')->nullable()->after('city');
            $table->unsignedBigInteger('governorate_id')->nullable()->after('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_location_idx');
        });

        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });
        }

        $columns = [
            'governorate_id', 'city_id', 'gender',
            'push_notifications', 'email_notifications', 'notifications_enabled', 'avatar',
            'last_location_lng', 'last_location_lat', 'otp_expires_at', 'otp_code', 'is_blocked',
            'role_id', 'language', 'city', 'country', 'phone',
        ];
        foreach ($columns as $col) {
            if (Schema::hasColumn('users', $col)) {
                Schema::table('users', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};

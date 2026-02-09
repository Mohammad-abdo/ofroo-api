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
            // Determine the column to add after (check in order: city, country, email_verified_at)
            $afterColumn = 'email_verified_at';
            if (Schema::hasColumn('users', 'city')) {
                $afterColumn = 'city';
            } elseif (Schema::hasColumn('users', 'country')) {
                $afterColumn = 'country';
            }
            
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after($afterColumn);
            }
            if (!Schema::hasColumn('users', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true)->after('avatar');
            }
            if (!Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true)->after('notifications_enabled');
            }
            if (!Schema::hasColumn('users', 'push_notifications')) {
                $table->boolean('push_notifications')->default(true)->after('email_notifications');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'notifications_enabled', 'email_notifications', 'push_notifications']);
        });
    }
};



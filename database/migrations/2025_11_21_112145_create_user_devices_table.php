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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->string('device_id', 255)->comment('Unique device identifier');
            $table->string('device_type', 50)->comment('Type: ios, android, web');
            $table->string('device_name', 255)->nullable()->comment('Device name/model');
            $table->string('os_version', 50)->nullable()->comment('OS version');
            $table->string('app_version', 50)->nullable()->comment('App version');
            $table->string('fcm_token', 500)->nullable()->comment('FCM push token');
            $table->string('ip_address', 45)->nullable()->comment('Last IP address');
            $table->timestamp('last_active_at')->nullable()->comment('Last activity timestamp');
            $table->boolean('is_active')->default(true)->comment('Device active status');
            $table->timestamps();
            
            $table->unique(['user_id', 'device_id']);
            $table->index('user_id');
            $table->index('device_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};

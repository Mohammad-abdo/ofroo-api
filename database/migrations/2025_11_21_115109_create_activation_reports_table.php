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
        Schema::create('activation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade')->comment('Coupon ID');
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant who activated');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User who owns coupon');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null')->comment('Related order');
            $table->string('activation_method', 50)->default('qr_scan')->comment('Method: qr_scan, manual, api');
            $table->string('device_id', 255)->nullable()->comment('Device used for activation');
            $table->string('ip_address', 45)->nullable()->comment('IP address');
            $table->string('location', 255)->nullable()->comment('Activation location (GPS)');
            $table->decimal('latitude', 10, 8)->nullable()->comment('Latitude');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Longitude');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->timestamps();
            
            $table->index('coupon_id');
            $table->index('merchant_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activation_reports');
    }
};

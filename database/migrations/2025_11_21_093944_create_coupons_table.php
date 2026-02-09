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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade')->comment('Order ID');
            $table->foreignId('offer_id')->constrained('offers')->onDelete('cascade')->comment('Offer ID');
            $table->string('coupon_code', 100)->unique()->comment('Unique coupon code');
            $table->string('barcode_value', 255)->nullable()->comment('Barcode value');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User owner ID');
            $table->enum('status', ['pending', 'reserved', 'paid', 'activated', 'used', 'cancelled', 'expired'])->default('pending')->comment('Coupon status: pending (cash before payment), reserved (cash after payment), paid (online payment), activated, used, cancelled, expired');
            $table->dateTime('reserved_at')->nullable()->comment('Reservation date');
            $table->dateTime('activated_at')->nullable()->comment('Activation date');
            $table->dateTime('used_at')->nullable()->comment('Usage date');
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('offer_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('coupon_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

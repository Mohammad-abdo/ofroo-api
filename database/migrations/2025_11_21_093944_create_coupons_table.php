<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Includes former migrations: modify_coupons (category, usage, created_by),
     * make_order_id_nullable, add_mall_id, add_expires/discount, terms + is_refundable.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade')->comment('Order ID');
            $table->foreignId('offer_id')->nullable()->constrained('offers')->onDelete('cascade')->comment('Offer ID');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('cascade')->comment('Optional category link');
            $table->foreignId('mall_id')->nullable()->constrained('malls')->nullOnDelete();
            $table->integer('usage_limit')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_type')->nullable();
            $table->integer('times_used')->default(0);
            $table->string('coupon_code', 100)->unique()->comment('Unique coupon code');
            $table->string('barcode_value', 255)->nullable()->comment('Barcode value');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User owner ID');
            $table->enum('status', ['pending', 'reserved', 'paid', 'activated', 'used', 'cancelled', 'expired'])->default('pending')->comment('Coupon status');
            $table->dateTime('reserved_at')->nullable()->comment('Reservation date');
            $table->dateTime('activated_at')->nullable()->comment('Activation date');
            $table->dateTime('used_at')->nullable()->comment('Usage date');
            $table->dateTime('expires_at')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable()->comment('Discount percentage (0-100)');
            $table->decimal('discount_amount', 10, 2)->nullable()->comment('Discount amount in currency');
            $table->enum('discount_type', ['percent', 'amount'])->default('percent')->comment('Discount type');
            $table->text('terms_conditions')->nullable();
            $table->boolean('is_refundable')->default(false);
            $table->timestamps();

            $table->index('order_id');
            $table->index('offer_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('coupon_code');
            $table->index('mall_id');
            $table->index('expires_at');
            $table->index(['status', 'expires_at'], 'coupons_status_expires_at_index');
            $table->index(['offer_id', 'status'], 'coupons_offer_id_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Child tables (e.g. coupon_entitlements) may still reference coupons when
        // rolling back in batch order — allow drop on MySQL.
        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('coupons');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};

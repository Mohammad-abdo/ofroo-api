<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runs after create_coupons: optional coupon link on offers, optional line-level coupon on cart_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id')->nullable()->unique()->after('category_id');
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('offer_id')->constrained('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn('coupon_id');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn('coupon_id');
        });
    }
};

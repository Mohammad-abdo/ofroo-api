<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories are the parent side of several relations.
 *
 * Foreign keys to categories live on the CHILD tables (correct 1-to-many / many-to-one shape):
 * - merchants.category_id  → categories.id (each merchant belongs to one category; see 2026_04_17_100000_add_category_id_foreign_key_to_merchants_table)
 * - offers.category_id     → categories.id (each offer is under one category)
 * - coupons.category_id    → categories.id (optional; also see offer_id → offers → category)
 *
 * Do NOT add merchant_id or coupon_id columns here: that would wrongly imply one merchant/coupon per category only.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150)->comment('اسم الفئة بالعربية');
            $table->string('name_en', 150)->nullable()->comment('Category name in English');
            $table->integer('order_index')->default(0)->comment('Display order');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade')->comment('Parent category ID');
            $table->timestamps();
            $table->boolean('is_active')->default(true)->comment('Active status');

            $table->index('parent_id');
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

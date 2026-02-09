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
        Schema::table('coupons', function (Blueprint $table) {
            // Add category_id (required - coupon must belong to a category)
            $table->unsignedBigInteger('category_id')->nullable()->after('offer_id');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            
            // Add usage_limit (number of times coupon can be used)
            $table->integer('usage_limit')->default(1)->after('category_id');
            
            // Add created_by to track who created the coupon (merchant or admin)
            $table->unsignedBigInteger('created_by')->nullable()->after('usage_limit');
            $table->string('created_by_type')->nullable()->after('created_by'); // 'merchant' or 'admin'
            
            // Add times_used counter
            $table->integer('times_used')->default(0)->after('usage_limit');
            
            // Make offer_id nullable since coupon can exist without offer initially
            // But when assigned to offer, it becomes required
            $table->unsignedBigInteger('offer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'usage_limit', 'created_by', 'created_by_type', 'times_used']);
            $table->unsignedBigInteger('offer_id')->nullable(false)->change();
        });
    }
};

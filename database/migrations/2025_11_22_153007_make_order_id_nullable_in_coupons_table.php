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
            // Make order_id nullable since coupons can be created by merchants/admins before orders
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     * Skip if order_id was already dropped (e.g. by refactor_offers_and_coupons_system).
     */
    public function down(): void
    {
        if (!Schema::hasColumn('coupons', 'order_id')) {
            return;
        }
        Schema::table('coupons', function (Blueprint $table) {
            \DB::table('coupons')->whereNull('order_id')->delete();
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }
};

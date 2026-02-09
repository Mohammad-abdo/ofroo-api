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
            // Add terms_conditions column
            $table->text('terms_conditions')->nullable()->after('expires_at');
            
            // Add is_refundable column
            $table->boolean('is_refundable')->default(false)->after('terms_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['terms_conditions', 'is_refundable']);
        });
    }
};


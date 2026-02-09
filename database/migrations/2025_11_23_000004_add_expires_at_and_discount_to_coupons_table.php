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
            // Add expires_at column
            $table->dateTime('expires_at')->nullable()->after('used_at');
            
            // Add discount fields
            $table->decimal('discount_percent', 5, 2)->nullable()->after('expires_at')->comment('Discount percentage (0-100)');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_percent')->comment('Discount amount in currency');
            $table->enum('discount_type', ['percent', 'amount'])->default('percent')->after('discount_amount')->comment('Discount type: percent or amount');
            
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     * Only drop columns that exist (refactor migration may have already dropped some).
     */
    public function down(): void
    {
        $columnsToDrop = [];
        foreach (['expires_at', 'discount_percent', 'discount_amount', 'discount_type'] as $col) {
            if (Schema::hasColumn('coupons', $col)) {
                $columnsToDrop[] = $col;
            }
        }
        if (!empty($columnsToDrop)) {
            Schema::table('coupons', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};


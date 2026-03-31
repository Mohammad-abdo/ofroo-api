<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Merchants table - composite index for approval filtering
        if (Schema::hasTable('merchants')) {
            Schema::table('merchants', function (Blueprint $table) {
                if (!Schema::hasIndex('merchants', 'merchants_approved_created_at_index')) {
                    $table->index(['approved', 'created_at'], 'merchants_approved_created_at_index');
                }
                if (!Schema::hasIndex('merchants', 'merchants_status_created_at_index') && Schema::hasColumn('merchants', 'is_blocked')) {
                    $table->index(['is_blocked', 'created_at'], 'merchants_status_created_at_index');
                }
            });
        }

        // Coupons table - composite index for activated coupon queries
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (!Schema::hasIndex('coupons', 'coupons_offer_id_status_index')) {
                    $table->index(['offer_id', 'status'], 'coupons_offer_id_status_index');
                }
            });
        }

        // Offers table - composite index for merchant's active offers
        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (!Schema::hasIndex('offers', 'offers_merchant_status_index')) {
                    $table->index(['merchant_id', 'status'], 'offers_merchant_status_index');
                }
                if (!Schema::hasIndex('offers', 'offers_category_status_index')) {
                    $table->index(['category_id', 'status'], 'offers_category_status_index');
                }
            });
        }

        // Orders table - for revenue calculations
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasIndex('orders', 'orders_payment_status_created_at_index')) {
                    $table->index(['payment_status', 'created_at'], 'orders_payment_status_created_at_index');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'merchants' => ['merchants_approved_created_at_index', 'merchants_status_created_at_index'],
            'coupons' => ['coupons_offer_id_status_index'],
            'offers' => ['offers_merchant_status_index', 'offers_category_status_index'],
            'orders' => ['orders_payment_status_created_at_index'],
        ];

        foreach ($tables as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                $existingIndexes = collect(DB::select("SHOW INDEXES FROM `{$table}`"))
                    ->pluck('Key_name')
                    ->toArray();

                foreach ($indexes as $index) {
                    if (in_array($index, $existingIndexes)) {
                        try {
                            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
                        } catch (\Exception $e) {
                            // Index might not exist
                        }
                    }
                }
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
    }
};

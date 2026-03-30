<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Coupons table indexes
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (!Schema::hasIndex('coupons', 'coupons_offer_id_index')) {
                    $table->index('offer_id', 'coupons_offer_id_index');
                }
                if (!Schema::hasIndex('coupons', 'coupons_status_expires_at_index')) {
                    $table->index(['status', 'expires_at'], 'coupons_status_expires_at_index');
                }
                if (!Schema::hasIndex('coupons', 'coupons_user_id_index')) {
                    $table->index('user_id', 'coupons_user_id_index');
                }
            });
        }

        // Orders table indexes
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasIndex('orders', 'orders_user_id_created_at_index')) {
                    $table->index(['user_id', 'created_at'], 'orders_user_id_created_at_index');
                }
                if (!Schema::hasIndex('orders', 'orders_merchant_payment_status_index')) {
                    $table->index(['merchant_id', 'payment_status', 'created_at'], 'orders_merchant_payment_status_index');
                }
            });
        }

        // Offers table indexes
        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (!Schema::hasIndex('offers', 'offers_merchant_id_index')) {
                    $table->index('merchant_id', 'offers_merchant_id_index');
                }
                if (!Schema::hasIndex('offers', 'offers_category_id_index')) {
                    $table->index('category_id', 'offers_category_id_index');
                }
                if (!Schema::hasIndex('offers', 'offers_mall_id_index')) {
                    $table->index('mall_id', 'offers_mall_id_index');
                }
            });
        }

        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasIndex('users', 'users_role_id_index')) {
                    $table->index('role_id', 'users_role_id_index');
                }
                if (!Schema::hasIndex('users', 'users_email_index')) {
                    $table->index('email', 'users_email_index');
                }
                if (!Schema::hasIndex('users', 'users_phone_index')) {
                    $table->index('phone', 'users_phone_index');
                }
            });
        }

        // Wallet transactions indexes
        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                if (!Schema::hasIndex('wallet_transactions', 'wallet_transactions_wallet_id_index')) {
                    $table->index('wallet_id', 'wallet_transactions_wallet_id_index');
                }
                if (!Schema::hasIndex('wallet_transactions', 'wallet_transactions_type_index')) {
                    $table->index('transaction_type', 'wallet_transactions_type_index');
                }
            });
        }

        // Financial transactions indexes
        if (Schema::hasTable('financial_transactions')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                if (!Schema::hasIndex('financial_transactions', 'financial_transactions_merchant_id_index')) {
                    $table->index('merchant_id', 'financial_transactions_merchant_id_index');
                }
                if (!Schema::hasIndex('financial_transactions', 'financial_transactions_type_flow_index')) {
                    $table->index(['transaction_type', 'transaction_flow'], 'financial_transactions_type_flow_index');
                }
            });
        }

        // Reviews indexes
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (!Schema::hasIndex('reviews', 'reviews_merchant_id_index')) {
                    $table->index('merchant_id', 'reviews_merchant_id_index');
                }
                if (!Schema::hasIndex('reviews', 'reviews_user_id_index')) {
                    $table->index('user_id', 'reviews_user_id_index');
                }
            });
        }

        // Merchant wallet indexes
        if (Schema::hasTable('merchant_wallets')) {
            Schema::table('merchant_wallets', function (Blueprint $table) {
                if (!Schema::hasIndex('merchant_wallets', 'merchant_wallets_merchant_id_unique')) {
                    $table->unique('merchant_id', 'merchant_wallets_merchant_id_unique');
                }
            });
        }

        // Offer branch pivot table indexes
        if (Schema::hasTable('offer_branch')) {
            Schema::table('offer_branch', function (Blueprint $table) {
                if (!Schema::hasIndex('offer_branch', 'offer_branch_offer_id_index')) {
                    $table->index('offer_id', 'offer_branch_offer_id_index');
                }
                if (!Schema::hasIndex('offer_branch', 'offer_branch_branch_id_index')) {
                    $table->index('branch_id', 'offer_branch_branch_id_index');
                }
            });
        }

        // Activity logs indexes (only if columns exist)
        if (Schema::hasTable('activity_logs')) {
            if (Schema::hasColumn('activity_logs', 'user_id')) {
                Schema::table('activity_logs', function (Blueprint $table) {
                    if (!Schema::hasIndex('activity_logs', 'activity_logs_user_id_index')) {
                        $table->index('user_id', 'activity_logs_user_id_index');
                    }
                });
            }
        }

        // Notifications indexes (only if columns exist)
        if (Schema::hasTable('notifications')) {
            if (Schema::hasColumn('notifications', 'notifiable_type') && Schema::hasColumn('notifications', 'notifiable_id')) {
                Schema::table('notifications', function (Blueprint $table) {
                    if (!Schema::hasIndex('notifications', 'notifications_notifiable_type_notifiable_id_index')) {
                        $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'coupons' => ['coupons_offer_id_index', 'coupons_status_expires_at_index', 'coupons_user_id_index'],
            'orders' => ['orders_user_id_created_at_index', 'orders_merchant_payment_status_index'],
            'offers' => ['offers_merchant_id_index', 'offers_category_id_index', 'offers_mall_id_index'],
            'users' => ['users_role_id_index', 'users_email_index', 'users_phone_index'],
            'wallet_transactions' => ['wallet_transactions_wallet_id_index', 'wallet_transactions_type_index'],
            'financial_transactions' => ['financial_transactions_merchant_id_index', 'financial_transactions_type_flow_index'],
            'reviews' => ['reviews_merchant_id_index', 'reviews_user_id_index'],
            'offer_branch' => ['offer_branch_offer_id_index', 'offer_branch_branch_id_index'],
            'activity_logs' => ['activity_logs_user_id_index'],
            'notifications' => ['notifications_notifiable_type_notifiable_id_index'],
            'merchant_wallets' => ['merchant_wallets_merchant_id_unique'],
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
                            // Index might not exist, ignore
                        }
                    }
                }
            } catch (\Exception $e) {
                // Table might not exist
            }
        }
    }
};

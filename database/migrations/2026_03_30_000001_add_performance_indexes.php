<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
                if (!Schema::hasIndex('offers', 'offers_status_dates_index')) {
                    $table->index(['status', 'start_date', 'end_date'], 'offers_status_dates_index');
                }
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

        // offer_branch: foreignId() already creates indexes required by FKs — do not add/drop here

        // Activity logs indexes (schema uses target_type/target_id, not subject_*)
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                if (Schema::hasColumn('activity_logs', 'user_id')
                    && !Schema::hasIndex('activity_logs', 'activity_logs_user_id_index')) {
                    $table->index('user_id', 'activity_logs_user_id_index');
                }
                if (Schema::hasColumn('activity_logs', 'target_type')
                    && Schema::hasColumn('activity_logs', 'target_id')
                    && !Schema::hasIndex('activity_logs', 'activity_logs_target_type_target_id_index')) {
                    $table->index(
                        ['target_type', 'target_id'],
                        'activity_logs_target_type_target_id_index'
                    );
                }
            });
        }

        // Notifications indexes
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasIndex('notifications', 'notifications_notifiable_type_notifiable_id_index')) {
                    $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        /*
         * Only drop indexes this migration may have created.
         * Do NOT drop single-column indexes that already exist from earlier
         * migrations on FK columns — MySQL will reject dropping them (error 1553).
         */
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (Schema::hasIndex('coupons', 'coupons_status_expires_at_index')) {
                    $table->dropIndex('coupons_status_expires_at_index');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasIndex('orders', 'orders_user_id_created_at_index')) {
                    $table->dropIndex('orders_user_id_created_at_index');
                }
                if (Schema::hasIndex('orders', 'orders_merchant_payment_status_index')) {
                    $table->dropIndex('orders_merchant_payment_status_index');
                }
            });
        }

        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (Schema::hasIndex('offers', 'offers_status_dates_index')) {
                    $table->dropIndex('offers_status_dates_index');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Never drop users_role_id_index — backs role_id foreign key
                if (Schema::hasIndex('users', 'users_email_index')) {
                    $table->dropIndex('users_email_index');
                }
                if (Schema::hasIndex('users', 'users_phone_index')) {
                    $table->dropIndex('users_phone_index');
                }
            });
        }

        if (Schema::hasTable('financial_transactions')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                if (Schema::hasIndex('financial_transactions', 'financial_transactions_type_flow_index')) {
                    $table->dropIndex('financial_transactions_type_flow_index');
                }
            });
        }

        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                if (Schema::hasIndex('activity_logs', 'activity_logs_target_type_target_id_index')) {
                    $table->dropIndex('activity_logs_target_type_target_id_index');
                }
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasIndex('notifications', 'notifications_notifiable_type_notifiable_id_index')) {
                    $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
                }
            });
        }
    }
};

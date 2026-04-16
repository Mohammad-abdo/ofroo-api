<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedInteger('usage_limit');
            $table->unsignedInteger('times_used')->default(0);
            $table->unsignedInteger('reserved_shares_count')->default(0);
            $table->string('status', 32)->default('pending');
            $table->string('redeem_token', 80)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'coupon_id']);
            $table->index('order_id');
        });

        Schema::create('coupon_entitlement_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_entitlement_id')->constrained('coupon_entitlements')->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->string('status', 32)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('parent_entitlement_id');
        });

        Schema::table('activation_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('activation_reports', 'coupon_entitlement_id')) {
                $table->foreignId('coupon_entitlement_id')
                    ->nullable()
                    ->after('coupon_id')
                    ->constrained('coupon_entitlements')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('activation_reports', 'coupon_entitlement_share_id')) {
                $table->foreignId('coupon_entitlement_share_id')
                    ->nullable()
                    ->after('coupon_entitlement_id')
                    ->constrained('coupon_entitlement_shares')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('activation_reports', function (Blueprint $table) {
            if (Schema::hasColumn('activation_reports', 'coupon_entitlement_share_id')) {
                $table->dropForeign(['coupon_entitlement_share_id']);
                $table->dropColumn('coupon_entitlement_share_id');
            }
            if (Schema::hasColumn('activation_reports', 'coupon_entitlement_id')) {
                $table->dropForeign(['coupon_entitlement_id']);
                $table->dropColumn('coupon_entitlement_id');
            }
        });

        Schema::dropIfExists('coupon_entitlement_shares');
        Schema::dropIfExists('coupon_entitlements');
    }
};

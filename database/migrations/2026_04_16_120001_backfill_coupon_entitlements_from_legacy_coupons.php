<?php

use App\Models\CouponEntitlement;
use App\Services\CouponService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy rows: coupons with user_id + order_id (instance-style purchases).
     * Creates one entitlement per such row so wallet tokens exist for existing data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('coupon_entitlements') || ! Schema::hasTable('coupons')) {
            return;
        }

        $couponService = app(CouponService::class);

        DB::table('coupons')
            ->whereNotNull('user_id')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($couponService) {
                foreach ($rows as $c) {
                    $exists = DB::table('coupon_entitlements')
                        ->where('order_id', $c->order_id)
                        ->where('coupon_id', $c->id)
                        ->where('user_id', $c->user_id)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    if (in_array((string) ($c->status ?? ''), ['cancelled', 'expired'], true)) {
                        continue;
                    }

                    $limit = max(1, (int) ($c->usage_limit ?? 1));
                    $used = min($limit, (int) ($c->times_used ?? 0));
                    $status = $used >= $limit ? 'exhausted' : 'active';

                    CouponEntitlement::create([
                        'user_id' => (int) $c->user_id,
                        'coupon_id' => (int) $c->id,
                        'order_id' => (int) $c->order_id,
                        'order_item_id' => null,
                        'usage_limit' => $limit,
                        'times_used' => $used,
                        'reserved_shares_count' => 0,
                        'status' => $status,
                        'redeem_token' => $couponService->generateWalletRedeemToken(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive: do not delete entitlements created by application logic.
    }
};

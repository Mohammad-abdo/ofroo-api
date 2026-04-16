<?php

namespace App\Services;

use App\Models\AppCouponSetting;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\CouponEntitlementShare;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponService
{
    /**
     * Generate unique barcode for a coupon template.
     */
    public function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'CUP-' . strtoupper(Str::random(10));
        } while (Coupon::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Create a coupon template row.
     */
    public function createCoupon(array $data): Coupon
    {
        if (! isset($data['barcode'])) {
            $data['barcode'] = $this->generateUniqueBarcode();
        }
        if (! isset($data['coupon_setting_id'])) {
            $data['coupon_setting_id'] = AppCouponSetting::current()->id;
        }

        return Coupon::create($data);
    }

    public function expireCoupons(): int
    {
        return Coupon::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Wallet token for staff scanner (long-lived bearer).
     */
    public function generateWalletRedeemToken(): string
    {
        do {
            $token = 'W-' . strtoupper(Str::random(32));
        } while (CouponEntitlement::where('redeem_token', $token)->exists());

        return $token;
    }

    /**
     * One-time friend share token.
     */
    public function generateShareToken(): string
    {
        do {
            $token = 'S-' . strtoupper(Str::random(32));
        } while (CouponEntitlementShare::where('token', $token)->exists());

        return $token;
    }

    /**
     * Resolve the offer's coupon template (single definition per offer).
     */
    public function resolveTemplateCouponForOffer(Offer $offer): ?Coupon
    {
        if ($offer->coupon_id) {
            $c = Coupon::where('id', $offer->coupon_id)->where('offer_id', $offer->id)->first();

            return $c ?? Coupon::find($offer->coupon_id);
        }

        return $offer->coupons()->orderBy('id')->first();
    }

    /**
     * After checkout: one entitlement row per order line with total uses = quantity (per unit = 1 redemption).
     */
    public function createCouponsForOrder(Order $order, ?Offer $offerIgnored = null, ?int $quantityIgnored = null): void
    {
        $order->loadMissing(['items.offer']);

        $paymentStatus = (string) ($order->payment_status ?? '');
        $entitlementStatus = $paymentStatus === 'paid' ? 'active' : 'pending';

        foreach ($order->items as $item) {
            $offer = $item->offer;
            if (! $offer) {
                continue;
            }

            $template = $this->resolveTemplateCouponForOffer($offer);
            if (! $template) {
                continue;
            }

            $qty = max(0, (int) $item->quantity);
            if ($qty === 0) {
                continue;
            }

            CouponEntitlement::create([
                'user_id' => $order->user_id,
                'coupon_id' => $template->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'usage_limit' => $qty,
                'times_used' => 0,
                'reserved_shares_count' => 0,
                'status' => $entitlementStatus,
                'redeem_token' => $this->generateWalletRedeemToken(),
            ]);
        }
    }

    public function updateCouponStatusAfterPayment(Order $order): void
    {
        CouponEntitlement::where('order_id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'active']);
    }

    /**
     * Pending shares past expires_at: mark expired and release reserved slots on parent entitlements.
     */
    public function releaseExpiredShares(): int
    {
        $released = 0;
        $ids = CouponEntitlementShare::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->pluck('id');

        foreach ($ids as $shareId) {
            DB::transaction(function () use ($shareId, &$released) {
                $s = CouponEntitlementShare::where('id', $shareId)->lockForUpdate()->first();
                if (! $s || $s->status !== 'pending') {
                    return;
                }
                $parent = CouponEntitlement::where('id', $s->parent_entitlement_id)->lockForUpdate()->first();
                if ($parent && (int) $parent->reserved_shares_count > 0) {
                    $parent->reserved_shares_count = (int) $parent->reserved_shares_count - 1;
                    $parent->save();
                }
                $s->update(['status' => 'expired']);
                $released++;
            });
        }

        return $released;
    }
}

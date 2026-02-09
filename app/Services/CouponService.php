<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Str;

class CouponService
{
    /**
     * Generate unique barcode for a coupon.
     */
    public function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'CUP-' . strtoupper(Str::random(10));
        } while (Coupon::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Create a coupon.
     */
    public function createCoupon(array $data): Coupon
    {
        if (!isset($data['barcode'])) {
            $data['barcode'] = $this->generateUniqueBarcode();
        }
        
        return Coupon::create($data);
    }

    /**
     * Expire coupons based on date or offer status.
     */
    public function expireCoupons(): int
    {
        return Coupon::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}

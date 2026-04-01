<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * Prefer non-empty scannable code: barcode, coupon_code, then legacy barcode_value.
     * Note: ?? does not treat '' as missing, so we must trim and fall through explicitly.
     */
    protected function resolveScannableCode(): string
    {
        foreach (['barcode', 'coupon_code', 'barcode_value'] as $col) {
            $v = $this->resource->getAttribute($col);
            if ($v !== null && $v !== '') {
                $s = trim((string) $v);
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return '';
    }

    public function toArray(Request $request): array
    {
        $usageLimitRaw = $this->usage_limit;
        if ($usageLimitRaw === null) {
            $usageLimit = 1;
            $unlimited = false;
        } else {
            $usageLimit = (int) $usageLimitRaw;
            $unlimited = $usageLimit === 0;
        }
        $timesUsed = (int) ($this->times_used ?? 0);
        if ($unlimited) {
            $remaining = null;
            $isExhausted = false;
        } else {
            $remaining = max(0, $usageLimit - $timesUsed);
            $isExhausted = $remaining <= 0;
        }

        $price = (float) $this->price;
        $priceAfterDiscount = (float) $this->price_after_discount;

        $dt = strtolower((string) ($this->discount_type ?? 'percent'));
        $discountTypeLabel = in_array($dt, ['amount', 'fixed'], true) ? 'fixed' : 'percentage';

        $scannable = $this->resolveScannableCode();

        $arr = [
            'id' => $this->id,
            'offer_id' => (string) $this->offer_id,
            'image' => $this->image ?? '',
            'title' => $this->title ?? '',
            'title_ar' => $this->title_ar ?? '',
            'title_en' => $this->title_en ?? '',
            'description' => $this->description ?? '',
            'description_ar' => $this->description_ar ?? '',
            'description_en' => $this->description_en ?? '',
            'price' => $price,
            'price_after_discount' => $priceAfterDiscount,
            'discount' => (float) $this->discount,
            'discount_type' => $discountTypeLabel,
            // Single canonical value for UIs (barcode image, QR, scan)
            'barcode' => $scannable,
            'coupon_code' => $scannable,
            'barcode_value' => $this->barcode_value !== null && $this->barcode_value !== '' ? (string) $this->barcode_value : '',
            'starts_at' => $this->starts_at ? $this->starts_at->toIso8601String() : '',
            'expires_at' => $this->expires_at ? $this->expires_at->toIso8601String() : '',
            'status' => $this->status ?? '',
            'usage_limit' => $unlimited ? 0 : $usageLimit,
            'usage_unlimited' => $unlimited,
            'times_used' => $timesUsed,
            'usage_remaining' => $remaining,
            'is_exhausted' => $isExhausted,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : '',
        ];
        if ($this->relationLoaded('offer') && $this->offer) {
            $o = $this->offer;
            $arr['offer'] = [
                'id' => $o->id,
                'merchant_id' => $o->merchant_id ?? null,
                'title' => $o->title ?? $o->title_ar ?? $o->title_en ?? null,
                'title_ar' => $o->title_ar ?? $o->title ?? null,
                'title_en' => $o->title_en ?? $o->title ?? null,
                'status' => $o->status ?? null,
                'start_date' => $o->start_date ?? null,
                'end_date' => $o->end_date ?? null,
            ];
        }
        return $arr;
    }
}

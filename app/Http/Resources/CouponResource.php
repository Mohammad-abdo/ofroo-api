<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usageLimit = (int) ($this->usage_limit ?? 1);
        $timesUsed = (int) ($this->times_used ?? 0);
        $remaining = max(0, $usageLimit - $timesUsed);

        $price = (float) $this->price;
        $priceAfterDiscount = (float) $this->price_after_discount;

        $arr = [
            'id' => $this->id,
            'offer_id' => (string) $this->offer_id,
            'image' => $this->image ?? '',
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'price' => $price,
            'price_after_discount' => $priceAfterDiscount,
            'discount' => (float) $this->discount,
            'discount_type' => $this->discount_type ?? 'percentage',
            'barcode' => $this->barcode ?? $this->coupon_code ?? '',
            'coupon_code' => $this->coupon_code ?? $this->barcode ?? '',
            'expires_at' => $this->expires_at ? $this->expires_at->toIso8601String() : '',
            'status' => $this->status ?? '',
            'usage_limit' => $usageLimit,
            'times_used' => $timesUsed,
            'usage_remaining' => $remaining,
            'is_exhausted' => $remaining <= 0,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : '',
        ];
        if ($this->relationLoaded('offer')) {
            $arr['offer'] = $this->offer ? [
                'id' => $this->offer->id,
                'title' => $this->offer->title ?? $this->offer->title_ar ?? $this->offer->title_en ?? null,
            ] : null;
        }
        return $arr;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'image' => $this->image,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'discount_type' => $this->discount_type ?? 'percentage',
            'barcode' => $this->barcode ?? $this->coupon_code ?? null,
            'coupon_code' => $this->coupon_code ?? $this->barcode ?? null,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
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

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'offer' => [
                            'id' => $item->offer->id,
                            'title_ar' => $item->offer->title_ar,
                            'title_en' => $item->offer->title_en,
                        ],
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                    ];
                });
            }),
            'coupons' => $this->whenLoaded('coupons', function () {
                return $this->coupons->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'coupon_code' => $coupon->coupon_code,
                        'barcode_value' => $coupon->barcode_value,
                        'status' => $coupon->status,
                        'reserved_at' => $coupon->reserved_at ? $coupon->reserved_at->toIso8601String() : null,
                        'activated_at' => $coupon->activated_at ? $coupon->activated_at->toIso8601String() : null,
                    ];
                });
            }),
            'merchant' => $this->whenLoaded('merchant', function () {
                return [
                    'id' => $this->merchant->id,
                    'company_name' => $this->merchant->company_name,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

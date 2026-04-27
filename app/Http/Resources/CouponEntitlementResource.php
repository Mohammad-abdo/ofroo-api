<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponEntitlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $remaining = $this->resource->remainingUses();

        $paidQuantity = ($this->relationLoaded('orderItem') && $this->orderItem !== null)
            ? (int) $this->orderItem->quantity
            : (int) $this->usage_limit;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'coupon_id' => $this->coupon_id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'usage_limit' => (int) $this->usage_limit,
            'paid_quantity' => $paidQuantity,
            'times_used' => (int) $this->times_used,
            'reserved_shares_count' => (int) $this->reserved_shares_count,
            'remaining_uses' => $remaining,
            'status' => $this->status,
            'redeem_token' => $this->redeem_token,
            'wallet_qr_value' => $this->redeem_token,
            'created_at' => $this->created_at?->toIso8601String(),
            'coupon' => $this->whenLoaded('coupon', function () {
                return new CouponResource($this->coupon);
            }),
            'order' => $this->whenLoaded('order', function () {
                if (! $this->order) {
                    return null;
                }

                return [
                    'id' => $this->order->id,
                    'total_amount' => (float) $this->order->total_amount,
                    'payment_status' => $this->order->payment_status,
                    'payment_method' => $this->order->payment_method,
                    'created_at' => $this->order->created_at?->toIso8601String(),
                ];
            }),
            'order_item' => $this->whenLoaded('orderItem', function () {
                if (! $this->orderItem) {
                    return null;
                }

                return [
                    'id' => $this->orderItem->id,
                    'offer_id' => $this->orderItem->offer_id,
                    'quantity' => (int) $this->orderItem->quantity,
                    'unit_price' => (float) $this->orderItem->unit_price,
                    'total_price' => (float) $this->orderItem->total_price,
                ];
            }),
        ];
    }
}

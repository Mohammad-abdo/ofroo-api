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

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'coupon_id' => $this->coupon_id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'usage_limit' => (int) $this->usage_limit,
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
        ];
    }
}

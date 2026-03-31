<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->when(
                $this->relationLoaded('merchant') && $this->merchant,
                fn () => $this->merchant->company_name ?? null
            ),
            'commission_rate' => round(($this->commission_rate ?? 0) * 100, 2),
            'commission_amount' => (float) ($this->commission_amount ?? 0),
            'order_amount' => $this->when(
                $this->relationLoaded('order') && $this->order,
                fn () => (float) $this->order->total_amount
            ),
            'status' => $this->status ?? 'completed',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

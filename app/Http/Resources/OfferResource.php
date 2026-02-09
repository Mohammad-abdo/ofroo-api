<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'merchant_id' => $this->merchant_id,
            'category_id' => $this->category_id,
            'mall_id' => $this->mall_id,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'offer_images' => $this->offer_images ?? [],
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'location' => $this->location,
            'status' => $this->status,
            'is_favorite' => $user ? $this->favoritedBy()->where('user_id', $user->id)->exists() : false,
            'merchant' => new MerchantResource($this->whenLoaded('merchant')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'mall' => new MallResource($this->whenLoaded('mall')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'coupons' => CouponResource::collection($this->whenLoaded('coupons')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

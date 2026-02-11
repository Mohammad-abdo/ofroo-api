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
            'start_date' => $this->start_date ? $this->start_date->toIso8601String() : null,
            'end_date' => $this->end_date ? $this->end_date->toIso8601String() : null,
            'location' => $this->location,
            'status' => $this->status,
            'is_favorite' => $user ? $this->favoritedBy()->where('user_id', $user->id)->exists() : false,
            'merchant' => $this->when($this->relationLoaded('merchant') && $this->merchant, fn () => new MerchantResource($this->merchant)),
            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => new CategoryResource($this->category)),
            'mall' => $this->when($this->relationLoaded('mall') && $this->mall, fn () => new MallResource($this->mall)),
            'branches' => $this->when($this->relationLoaded('branches'), fn () => BranchResource::collection($this->branches)),
            'coupons' => $this->when($this->relationLoaded('coupons'), fn () => CouponResource::collection($this->coupons)),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

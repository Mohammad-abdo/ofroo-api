<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $merchantData = null;
        if ($this->relationLoaded('merchant') && $this->merchant) {
            $merchantData = (new MerchantResource($this->merchant, false))->toArray($request);
        }
        $mallData = $this->relationLoaded('mall') && $this->mall
            ? (new MallResource($this->mall))->toArray($request)
            : [];

        return [
            'id' => $this->id,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'merchant_id' => (string) $this->merchant_id,
            'category_id' => (string) $this->category_id,
            'mall_id' => $this->mall_id !== null ? (string) $this->mall_id : null,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'offer_images' => $this->offer_images ?? [],
            'start_date' => $this->start_date ? $this->start_date->toIso8601String() : null,
            'end_date' => $this->end_date ? $this->end_date->toIso8601String() : null,
            'location' => $this->location ?? '',
            'status' => $this->status ?? '',
            'is_favorite' => $user ? $this->favoritedBy()->where('user_id', $user->id)->exists() : false,
            'merchant' => $merchantData,
            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => new CategoryResource($this->category)),
            'mall' => $mallData,
            'branches' => $this->when($this->relationLoaded('branches'), fn () => BranchResource::collection($this->branches)),
            'coupons' => $this->when($this->relationLoaded('coupons'), fn () => CouponResource::collection($this->coupons)),
            'terms_conditions_ar' => $this->terms_conditions_ar ?? '',
            'terms_conditions_en' => $this->terms_conditions_en ?? '',
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : '',
        ];
    }
}

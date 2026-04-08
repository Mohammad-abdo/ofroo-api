<?php

namespace App\Http\Resources;

use App\Support\ApiMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ?? '',
            'title_ar' => $this->title ?? '',
            'title_en' => $this->title_en ?? '',
            'description' => $this->description ?? '',
            'description_ar' => $this->description ?? '',
            'description_en' => $this->description_en ?? '',
            'merchant_id' => (string) $this->merchant_id,
            'category_id' => (string) $this->category_id,
            'mall_id' => $this->mall_id !== null ? (string) $this->mall_id : null,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'offer_images' => ApiMediaUrl::absoluteList($this->offer_images ?? []),
            'start_date' => $this->start_date ? $this->start_date->toIso8601String() : null,
            'end_date' => $this->end_date ? $this->end_date->toIso8601String() : null,
            'status' => $this->status ?? '',
            'merchant_name' => $this->when(
                $this->relationLoaded('merchant') && $this->merchant,
                fn () => $this->merchant->company_name ?? null
            ),
            'category_name' => $this->when(
                $this->relationLoaded('category') && $this->category,
                fn () => $this->category->name_ar ?? $this->category->name_en ?? null
            ),
            'coupons_count' => $this->when(
                $this->relationLoaded('coupons'),
                fn () => $this->coupons->count()
            ),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : '',
        ];
    }
}

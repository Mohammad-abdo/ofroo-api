<?php

namespace App\Http\Resources;

use App\Support\ApiMediaUrl;
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
            /** Arabic / primary title (same as DB `title` after refactor) */
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
            'location' => $this->location ?? '',
            'status' => $this->status ?? '',
            'is_expired' => $this->resource->isExpired(),
            // Legacy/typo compatibility for some mobile clients
            'is_expire' => $this->resource->isExpired(),
            'is_not_started' => $this->resource->isNotYetStarted(),
            'effective_status' => $this->resource->effectiveStatus(),
            'status_label_ar' => $this->offerStatusLabelAr(),
            'status_label_en' => $this->offerStatusLabelEn(),
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

    private function offerStatusLabelAr(): string
    {
        if ($this->resource->isExpired()) {
            return 'هذا العرض منتهي';
        }
        if ($this->resource->isNotYetStarted()) {
            return 'العرض لم يبدأ بعد';
        }

        return '';
    }

    private function offerStatusLabelEn(): string
    {
        if ($this->resource->isExpired()) {
            return 'This offer has expired';
        }
        if ($this->resource->isNotYetStarted()) {
            return 'This offer has not started yet';
        }

        return '';
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('coupons')) {
            $coupons = $this->coupons;
            if (is_string($coupons)) {
                $decoded = json_decode($coupons, true);
                $coupons = is_array($decoded) ? $decoded : [];
            }
            if (is_array($coupons)) {
                foreach ($coupons as $i => $c) {
                    if (isset($c['discount_type'])) {
                        $dt = $c['discount_type'];
                        if ($dt === 'percent' || $dt === 'amount') {
                            $coupons[$i]['discount_type'] = $dt === 'percent' ? 'percentage' : 'fixed';
                        }
                    }
                }
                $this->merge(['coupons' => $coupons]);
            } else {
                $this->merge(['coupons' => []]);
            }
        }

        $merge = [];
        if ($this->hasAny(['title_ar', 'title_en', 'title'])) {
            $titleAr = trim((string) ($this->input('title_ar') ?? ''));
            $titleEn = trim((string) ($this->input('title_en') ?? ''));
            $legacyTitle = trim((string) ($this->input('title') ?? ''));
            if ($titleAr === '' && $legacyTitle !== '') {
                $titleAr = $legacyTitle;
            }
            $primaryTitle = $titleAr !== '' ? $titleAr : $titleEn;
            $merge['title'] = $primaryTitle;
            $merge['title_en'] = $titleEn !== '' ? $titleEn : null;
        }
        if ($this->hasAny(['description_ar', 'description_en', 'description'])) {
            $descAr = trim((string) ($this->input('description_ar') ?? ''));
            $descEn = trim((string) ($this->input('description_en') ?? ''));
            $legacyDesc = trim((string) ($this->input('description') ?? ''));
            if ($descAr === '' && $legacyDesc !== '') {
                $descAr = $legacyDesc;
            }
            $primaryDesc = $descAr !== '' ? $descAr : $descEn;
            $merge['description'] = $primaryDesc !== '' ? $primaryDesc : null;
            $merge['description_en'] = $descEn !== '' ? $descEn : null;
        }
        if (!$this->filled('start_date') && $this->filled('start_at')) {
            $merge['start_date'] = $this->start_at;
        }
        if (!$this->filled('end_date') && $this->filled('end_at')) {
            $merge['end_date'] = $this->end_at;
        }
        if ($this->has('mall_id') && $this->mall_id === '') {
            $merge['mall_id'] = null;
        }
        if ($this->has('price') && ($this->price === '' || $this->price === null)) {
            $merge['price'] = 0;
        }
        if ($this->has('discount') && ($this->discount === '' || $this->discount === null)) {
            $merge['discount'] = 0;
        }
        if ($this->has('location')) {
            $loc = $this->location;
            if (is_string($loc)) {
                $decoded = json_decode($loc, true);
                $this->merge(['location' => is_array($decoded) ? $decoded : []]);
            } elseif (!is_array($loc)) {
                $this->merge(['location' => []]);
            }
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'sometimes|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'merchant_id' => 'nullable|exists:merchants,id',
            'category_id' => 'nullable|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'location' => 'nullable|array',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
            'coupons' => 'nullable|array',
            'coupons.*.title' => 'required_with:coupons|string|max:255',
            'coupons.*.description' => 'nullable|string',
            'coupons.*.price' => 'nullable|numeric|min:0',
            'coupons.*.discount' => 'nullable|numeric|min:0',
            'coupons.*.discount_type' => 'nullable|in:percentage,fixed,percent,amount',
            'coupons.*.barcode' => 'nullable|string|max:64',
            'coupons.*.image' => 'nullable|string',
            'coupons.*.status' => 'nullable|in:active,used,expired',
            'coupons.*.usage_limit' => 'nullable|integer|min:1',
        ];

        $contentType = $this->header('Content-Type', '');
        $isMultipart = $contentType && str_contains(strtolower($contentType), 'multipart/form-data');
        if ($isMultipart) {
            $rules['offer_images'] = 'nullable|array';
            $rules['offer_images.*'] = 'nullable';
            $rules['coupon_images'] = 'nullable|array';
            $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
            $rules['coupon_images.*'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:'.$maxKb;
        } else {
            $rules['offer_images'] = 'nullable|array';
        }

        return $rules;
    }
}

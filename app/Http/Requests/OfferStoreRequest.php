<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by Policy
    }

    protected function prepareForValidation(): void
    {
        // Decode coupons when sent as JSON string (e.g. FormData from frontend)
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
        $user = $this->user();
        if ($user && $user->merchant && !$this->filled('merchant_id')) {
            $this->merge(['merchant_id' => $user->merchant->id]);
        }
        // Map legacy field names to new (so clients sending title_ar, start_at, etc. still pass)
        $merge = [];
        if (!$this->filled('title') && $this->filled('title_ar')) {
            $merge['title'] = $this->title_ar;
        }
        if (!$this->filled('description') && $this->filled('description_ar')) {
            $merge['description'] = $this->description_ar;
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
        // Decode location when sent as JSON string (FormData)
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'merchant_id' => 'required|exists:merchants,id',
            'category_id' => 'required|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|array',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
            'coupons' => 'nullable|array',
            'coupons.*.title' => 'required|string|max:255',
            'coupons.*.description' => 'nullable|string',
            'coupons.*.price' => 'required|numeric|min:0',
            'coupons.*.discount' => 'nullable|numeric|min:0',
            'coupons.*.discount_type' => 'nullable|in:percentage,fixed,percent,amount',
            'coupons.*.barcode' => 'nullable|string|max:64',
            'coupons.*.image' => 'nullable|string',
            'coupons.*.status' => 'nullable|in:active,used,expired',
        ];

        $contentType = $this->header('Content-Type', '');
        $isMultipart = $contentType && str_contains(strtolower($contentType), 'multipart/form-data');
        if ($isMultipart) {
            $rules['offer_images'] = 'nullable|array';
            $rules['offer_images.*'] = 'nullable';
            $rules['coupon_images'] = 'nullable|array';
            $rules['coupon_images.*'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120';
        } else {
            $rules['offer_images'] = 'nullable|array';
        }

        return $rules;
    }
}

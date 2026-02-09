<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $offerId = $this->route('id');

        return [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'original_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $this->price && $value <= $this->price) {
                        $fail('The original price must be greater than the price.');
                    }
                },
            ],
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'nullable',
            'total_coupons' => 'required|integer|min:1',
            'start_at' => 'nullable|date',
            'end_at' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value && $this->start_at) {
                        if (strtotime($value) <= strtotime($this->start_at)) {
                            $fail('The end at field must be a date after start at.');
                        }
                    }
                },
            ],
            'category_id' => 'required|exists:categories,id',
            'location_id' => 'nullable|exists:store_locations,id',
            'coupon_id' => 'nullable|exists:coupons,id',
            'status' => 'nullable|in:draft,pending,active,expired',
        ];
    }
}

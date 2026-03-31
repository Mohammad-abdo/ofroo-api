<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('coupons') && is_string($this->coupons)) {
            $decoded = json_decode($this->coupons, true);
            if (is_array($decoded)) {
                $this->merge(['coupons' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'offer_id' => 'required|exists:offers,id',
            'quantity' => 'nullable|integer|min:1|max:10',
            'payment_method' => 'required|in:wallet,card, Fawry,knet,apple_pay',
            'coupons' => 'nullable|array',
            'coupons.*.coupon_id' => 'required|exists:coupons,id',
        ];
    }
}

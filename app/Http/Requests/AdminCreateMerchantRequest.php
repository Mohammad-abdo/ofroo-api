<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        foreach (['mall_id', 'category_id'] as $key) {
            if (isset($input[$key]) && $input[$key] === '') {
                $input[$key] = null;
            }
        }
        if (!empty($input['branches']) && is_array($input['branches'])) {
            foreach ($input['branches'] as $i => $b) {
                if (isset($b['mall_id']) && $b['mall_id'] === '') {
                    $input['branches'][$i]['mall_id'] = null;
                }
            }
        }
        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
            'language' => 'nullable|in:ar,en',
            'city' => 'nullable|string|max:255',
            'company_name' => 'required|string|max:255',
            'company_name_ar' => 'nullable|string|max:255',
            'company_name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'address_ar' => 'nullable|string|max:500',
            'address_en' => 'nullable|string|max:500',
            'commercial_registration' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'whatsapp_number' => 'nullable|string|max:50',
            'whatsapp_link' => 'nullable|string|max:500',
            'whatsapp_enabled' => 'nullable|boolean',
            'mall_id' => 'nullable|exists:malls,id',
            'category_id' => 'nullable|exists:categories,id',
            'is_approved' => 'nullable|boolean',
            'branches' => 'nullable|array',
            'branches.*.name' => 'required_with:branches|string|max:255',
            'branches.*.name_ar' => 'nullable|string|max:255',
            'branches.*.name_en' => 'nullable|string|max:255',
            'branches.*.address' => 'nullable|string|max:500',
            'branches.*.address_ar' => 'nullable|string|max:500',
            'branches.*.address_en' => 'nullable|string|max:500',
            'branches.*.phone' => 'nullable|string|max:50',
            'branches.*.mall_id' => 'nullable|exists:malls,id',
            'branches.*.lat' => 'nullable|numeric',
            'branches.*.lng' => 'nullable|numeric',
            'branches.*.is_active' => 'nullable|boolean',
        ];
    }
}

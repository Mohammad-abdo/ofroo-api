<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->route('id') . ',id',
            'phone' => 'sometimes|nullable|string|max:50',
            'company_name' => 'sometimes|string|max:255',
            'company_name_ar' => 'sometimes|nullable|string|max:255',
            'company_name_en' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'description_ar' => 'sometimes|nullable|string',
            'description_en' => 'sometimes|nullable|string',
            'address' => 'sometimes|nullable|string|max:500',
            'address_ar' => 'sometimes|nullable|string|max:500',
            'address_en' => 'sometimes|nullable|string|max:500',
            'commercial_registration' => 'sometimes|nullable|string|max:255',
            'tax_number' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'whatsapp_number' => 'sometimes|nullable|string|max:50',
            'whatsapp_link' => 'sometimes|nullable|string|max:500',
            'whatsapp_enabled' => 'sometimes|boolean',
            'mall_id' => 'sometimes|nullable|exists:malls,id',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'is_approved' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
        ];
    }
}

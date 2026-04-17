<?php

namespace App\Http\Requests;

use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class MerchantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['whatsapp_link', 'logo_url'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }
        if ($this->has('category_id') && ($this->input('category_id') === '' || $this->input('category_id') === null)) {
            $merge['category_id'] = null;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'company_name' => 'sometimes|string|max:255',
            'company_name_ar' => 'sometimes|string|max:255',
            'company_name_en' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'description_ar' => 'sometimes|string|nullable',
            'description_en' => 'sometimes|string|nullable',
            'address' => 'sometimes|string|max:500|nullable',
            'address_ar' => 'sometimes|string|max:500|nullable',
            'address_en' => 'sometimes|string|max:500|nullable',
            'phone' => 'sometimes|string|max:50|nullable',
            'whatsapp_number' => 'sometimes|string|max:50|nullable',
            // Allow wa.me/…, numbers, or partial links (admin UI often stores non-RFC strings).
            'whatsapp_link' => 'sometimes|nullable|string|max:255',
            'whatsapp_enabled' => 'sometimes|boolean',
            'city' => 'sometimes|string|max:255|nullable',
            // Stored value may be relative path or full URL — do not use strict `url` rule.
            'logo_url' => 'sometimes|nullable|string|max:500',
            'logo' => ImageUploadRules::sometimesFileMax(2048),
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'phone_user' => 'sometimes|string|max:50|nullable',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'logo.file' => 'Logo must be a valid file',
            'logo.mimes' => 'Logo must be a supported image type',
            'logo.max' => 'Logo must not be greater than 2MB',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}

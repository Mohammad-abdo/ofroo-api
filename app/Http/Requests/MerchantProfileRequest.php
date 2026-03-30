<?php

namespace App\Http\Requests;

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
            'whatsapp_link' => 'sometimes|url|max:255|nullable',
            'whatsapp_enabled' => 'sometimes|boolean',
            'city' => 'sometimes|string|max:255|nullable',
            'logo_url' => 'sometimes|url|max:500|nullable',
            'logo' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'phone_user' => 'sometimes|string|max:50|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'Logo must be an image file',
            'logo.mimes' => 'Logo must be a file of type: jpeg, png, jpg, gif, webp',
            'logo.max' => 'Logo must not be greater than 2MB',
            'logo_url.url' => 'Logo URL must be a valid URL',
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

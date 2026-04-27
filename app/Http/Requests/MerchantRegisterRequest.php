<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MerchantRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(function ($v) {
            return is_string($v) ? trim($v) : $v;
        }, $this->only([
            'company_name',
            'company_name_ar',
            'company_name_en',
            'city',
        ])));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $name = (string) $this->input('company_name', '');
            $ar = (string) $this->input('company_name_ar', '');
            $en = (string) $this->input('company_name_en', '');
            if ($name === '' && $ar === '' && $en === '') {
                $validator->errors()->add(
                    'company_name',
                    'يجب إدخال اسم الشركة (company_name أو company_name_ar أو company_name_en).'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'accepted_terms.required' => 'يجب الموافقة على الشروط والأحكام | You must accept the terms and conditions.',
            'accepted_terms.accepted'  => 'يجب الموافقة على الشروط والأحكام | You must accept the terms and conditions.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'language' => 'nullable|in:ar,en',
            'company_name' => 'nullable|string|max:255',
            'company_name_ar' => 'nullable|string|max:255',
            'company_name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'address_ar' => 'nullable|string|max:500',
            'address_en' => 'nullable|string|max:500',
            'phone_merchant' => 'nullable|string|max:50',
            'whatsapp_link' => 'nullable|url|max:255',
            // Accept any non-empty label (Arabic/English/slug); Flutter often sends English while admin used strict in: list before.
            'city' => 'required|string|max:255',
            'accepted_terms' => 'required|accepted',
        ];
    }
}

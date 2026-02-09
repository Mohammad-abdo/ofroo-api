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
            'company_name' => 'required|string|max:255',
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
            'city' => 'required|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
        ];
    }
}

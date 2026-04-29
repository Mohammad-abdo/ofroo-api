<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileLoginRequest extends FormRequest
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
     * تسجيل الدخول للموبايل برقم الهاتف وكلمة المرور فقط.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => 'required|string|exists:users,phone',
            'password' => 'required|string',
        ];
    }

    /**
     * JSON clients often send phone as a number — Laravel's `string` rule rejects it.
     * Do not rewrite digits here: DB may store local or +20 format; normalization is applied at registration.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_numeric($this->input('phone'))) {
            $this->merge(['phone' => (string) $this->input('phone')]);
        }
    }
}

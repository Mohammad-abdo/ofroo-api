<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileRegisterRequest extends FormRequest
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
            'password_confirmation' => 'required|string|same:password',
            'gender' => 'nullable|string|in:male,female,mal,fem',
            'language' => 'nullable|in:ar,en',
            'city' => 'nullable|integer',
            'governorate' => 'nullable|integer',
        ];
    }

    /**
     * Prepare the data for validation (normalize gender).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('gender')) {
            $gender = strtolower((string) $this->gender);
            if ($gender === 'mal') {
                $this->merge(['gender' => 'male']);
            }
            if ($gender === 'fem') {
                $this->merge(['gender' => 'female']);
            }
        }
    }
}

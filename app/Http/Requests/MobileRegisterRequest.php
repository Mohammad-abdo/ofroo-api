<?php

namespace App\Http\Requests;

use App\Models\City;
use App\Support\EgyptPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'city_id' => 'nullable|integer|exists:cities,id',
            'governorate_id' => 'nullable|integer|exists:governorates,id',
            // للتوافق مع الطلب القديم (نص المدينة)
            'city' => 'nullable|string|max:255',
            'governorate' => 'nullable|string|max:255',
        ];
    }

    /**
     * Ensure city_id belongs to governorate_id when both are sent.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $govId = $this->input('governorate_id');
            $cityId = $this->input('city_id');
            if ($govId === null || $govId === '' || $cityId === null || $cityId === '') {
                return;
            }

            $city = City::query()->find((int) $cityId);
            if (! $city) {
                return;
            }

            if ((int) $city->governorate_id !== (int) $govId) {
                $validator->errors()->add(
                    'city_id',
                    'المدينة لا تنتمي لهذه المحافظة. اطلب GET /api/mobile/cities?governorate_id=… بعد اختيار المحافظة واختر city_id من النتيجة. / City does not belong to the selected governorate.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation (normalize gender).
     */
    protected function prepareForValidation(): void
    {
        if ($this->missing('governorate_id') && $this->filled('governorateId')) {
            $this->merge(['governorate_id' => $this->input('governorateId')]);
        }
        if ($this->missing('city_id') && $this->filled('cityId')) {
            $this->merge(['city_id' => $this->input('cityId')]);
        }

        if ($this->has('gender')) {
            $gender = strtolower((string) $this->gender);
            if ($gender === 'mal') {
                $this->merge(['gender' => 'male']);
            }
            if ($gender === 'fem') {
                $this->merge(['gender' => 'female']);
            }
        }

        // JSON often sends phone as number — Laravel's `string` rule rejects it.
        if ($this->has('phone')) {
            $normalized = EgyptPhone::normalize($this->input('phone'));
            if ($normalized !== '') {
                $this->merge(['phone' => $normalized]);
            }
        }

        foreach (['name', 'email', 'city', 'governorate'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->filled('governorate_id')) {
            $this->merge(['governorate_id' => (int) $this->input('governorate_id')]);
        }
        if ($this->filled('city_id')) {
            $this->merge(['city_id' => (int) $this->input('city_id')]);
        }
    }
}

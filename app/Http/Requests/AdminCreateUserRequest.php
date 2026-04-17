<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && $u->canAccessAdminPanel()
            && ($u->isAdmin() || $u->hasPermission('users.create'));
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'city' => 'nullable|string|max:255',
            'language' => 'nullable|in:ar,en',
        ];
    }
}

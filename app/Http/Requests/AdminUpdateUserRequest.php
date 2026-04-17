<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && $u->canAccessAdminPanel()
            && ($u->isAdmin() || $u->hasPermission('users.update'));
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $userId,
            'phone' => 'nullable|string|max:50',
            'password' => 'sometimes|string|min:8|confirmed',
            'role_id' => 'sometimes|exists:roles,id',
            'city' => 'nullable|string|max:255',
            'language' => 'nullable|in:ar,en',
            'is_blocked' => 'nullable|boolean',
        ];
    }
}

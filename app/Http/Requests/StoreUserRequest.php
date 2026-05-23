<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() ? $this->user()->can('users.create') : true;
    }

    public function rules()
    {
        return [
            'username' => 'required|string|max:100|unique:users,username',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8',
            'preferred_language' => 'nullable|string|max:10',
            'is_active' => 'sometimes|boolean',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Assign at least one role.',
            'roles.min' => 'Assign at least one role.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('preferred_language')) {
            $this->merge(['preferred_language' => 'de']);
        }
    }
}

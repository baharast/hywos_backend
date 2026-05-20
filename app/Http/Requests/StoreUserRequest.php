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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'is_active' => 'sometimes|boolean',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }
}

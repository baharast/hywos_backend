<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() ? $this->user()->can('users.update') : true;
    }

    public function rules()
    {
        return [
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }
}

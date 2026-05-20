<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeUserStatusRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() ? $this->user()->can('users.update') : true;
    }

    public function rules()
    {
        return [
            'reason_code' => 'sometimes|string|max:100',
            'reason_note' => 'sometimes|string|max:255',
        ];
    }
}

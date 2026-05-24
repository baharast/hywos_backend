<?php

namespace App\Http\Requests\Tan;

use Illuminate\Foundation\Http\FormRequest;

class ExpireTanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}

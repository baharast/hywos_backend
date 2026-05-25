<?php

namespace App\Http\Requests\LoadingOrder;

use Illuminate\Foundation\Http\FormRequest;

class BlockLoadingOrderRequest extends FormRequest
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

<?php

namespace App\Http\Requests\LoadingOrder;

use Illuminate\Foundation\Http\FormRequest;

class UnblockLoadingOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}

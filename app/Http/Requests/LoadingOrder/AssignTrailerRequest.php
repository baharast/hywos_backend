<?php

namespace App\Http\Requests\LoadingOrder;

use Illuminate\Foundation\Http\FormRequest;

class AssignTrailerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'trailer_id' => 'required|string|size:36|exists:trailers,id',
        ];
    }
}

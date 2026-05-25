<?php

namespace App\Http\Requests\LoadingOrder;

use Illuminate\Foundation\Http\FormRequest;

class AssignDriverRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'driver_id' => 'required|string|size:36|exists:drivers,id',
        ];
    }
}

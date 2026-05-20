<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() ? $this->user()->can('customers.create') : true;
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:50|unique:customers,code',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'site_id' => 'nullable|string|max:36',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

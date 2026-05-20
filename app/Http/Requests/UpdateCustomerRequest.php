<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() ? $this->user()->can('customers.update') : true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'code' => 'required|string|max:50|unique:customers,code,' . $id . ',id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'site_id' => 'nullable|string|max:36',
            // is_active is intentionally omitted; use activate/deactivate endpoints
        ];
    }
}

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
            'legal_name' => 'nullable|string|max:255',
            'sap_customer_no' => 'nullable|string|max:50|unique:customers,sap_customer_no',
            'external_reference' => 'nullable|string|max:100',

            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            'primary_contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',

            'document_requirements' => 'nullable|array',
            'document_requirements.*' => 'string|max:50',
            'default_document_language' => 'nullable|string|max:10',

            'status' => 'nullable|in:active,inactive,blocked,archived',
            'notes' => 'nullable|string',

            'site_id' => 'nullable|string|max:36',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

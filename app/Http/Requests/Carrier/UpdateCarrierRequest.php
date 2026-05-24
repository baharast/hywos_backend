<?php

namespace App\Http\Requests\Carrier;

use App\Enums\CarrierApprovalState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarrierRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'carrier_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('freight_forwarders', 'carrier_code')->ignore($id),
            ],
            'carrier_name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'sap_reference' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('freight_forwarders', 'sap_reference')->ignore($id),
            ],
            'external_reference' => 'nullable|string|max:100',

            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            'primary_contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',

            'approval_state' => ['nullable', 'string', Rule::in(CarrierApprovalState::all())],
            'approved_for_loading' => 'nullable|boolean',

            'notes' => 'nullable|string',

            'is_active' => 'sometimes|boolean',
            'company_id' => 'nullable|string|size:36',

            // status, block_reason, blocked_at are intentionally omitted; use block/unblock endpoints
        ];
    }
}

<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
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
            'driver_code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('drivers', 'driver_code')->ignore($id),
            ],
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',

            'national_id_last4' => 'nullable|string|size:4',
            'license_no' => 'nullable|string|max:50',
            'license_expiry_date' => 'nullable|date',

            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',

            'preferred_culture_code' => 'sometimes|required|string|max:10',

            'training_status' => 'nullable|string|in:valid,expired,missing,not_required,unknown',
            'training_valid_until' => 'nullable|date',

            'employer_company_id' => 'nullable|string|size:36|exists:companies,id',
            'operator_company_id' => 'nullable|string|size:36|exists:companies,id',

            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',

            // block_status changes must go through block/unblock action endpoints
        ];
    }
}

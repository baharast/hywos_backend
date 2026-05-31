<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    /**
     * Strip any client-supplied `driver_code` BEFORE validation. The
     * code is a system identifier minted at create time and immutable
     * for the row's lifetime — drop it silently rather than failing.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('driver_code')) {
            $this->offsetUnset('driver_code');
        }
    }

    public function rules()
    {
        return [
            // driver_code intentionally absent — see prepareForValidation().
            // Identifier is system-managed and cannot be edited via PUT.
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

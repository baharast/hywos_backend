<?php

namespace App\Http\Requests\Trailer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrailerRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    /**
     * Strip any client-supplied `trailer_code` BEFORE validation so an
     * out-of-band edit is dropped silently rather than failing — the
     * code is a system identifier minted at create time and immutable
     * for the row's lifetime.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('trailer_code')) {
            $this->offsetUnset('trailer_code');
        }
    }

    public function rules()
    {
        return [
            // trailer_code intentionally absent — see prepareForValidation().
            // Identifier is system-managed and cannot be edited via PUT.
            'trailer_label' => 'nullable|string|max:150',
            'plate' => 'nullable|string|max:50',

            'trailer_type' => 'nullable|string|max:50',
            'pressure_class' => 'nullable|string|max:50',
            'volume' => 'nullable|numeric|min:0',
            'volume_unit' => 'nullable|string|max:10',

            'approved_product_quality' => 'nullable|array',
            'approved_product_quality.*' => 'string|max:50',

            'inspection_expiry_date' => 'nullable|date',
            'inspection_reference' => 'nullable|string|max:100',

            'technical_suitability' => 'nullable|string|in:approved,incomplete,not_suitable,needs_review',

            'carrier_id' => 'nullable|string|size:36|exists:companies,id',
            'customer_id' => 'nullable|string|size:36|exists:companies,id',

            'current_parking_id' => 'nullable|string|size:36',
            'current_context' => 'nullable|string|max:255',

            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            // status changes must use block/unblock action endpoints
        ];
    }
}

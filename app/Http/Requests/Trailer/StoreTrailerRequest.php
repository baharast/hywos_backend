<?php

namespace App\Http\Requests\Trailer;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrailerRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'trailer_code' => 'required|string|max:50|unique:trailers,trailer_code',
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
            'status' => 'nullable|string|in:active,inactive,blocked,archived',

            'carrier_id' => 'nullable|string|size:36|exists:companies,id',
            'customer_id' => 'nullable|string|size:36|exists:companies,id',

            'current_parking_id' => 'nullable|string|size:36',
            'current_context' => 'nullable|string|max:255',

            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}

<?php

namespace App\Http\Requests\Parking;

use Illuminate\Foundation\Http\FormRequest;

class ReserveSlotRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'reservation_for' => 'nullable|date',
            'linked_order_id' => 'nullable|string|size:36',
            'linked_order_no' => 'nullable|string|max:50',
            'driver_id' => 'nullable|string|size:36',
            'driver_name' => 'nullable|string|max:255',
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}

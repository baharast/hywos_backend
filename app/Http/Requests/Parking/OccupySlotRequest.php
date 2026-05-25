<?php

namespace App\Http\Requests\Parking;

use App\Enums\ParkingLoadState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OccupySlotRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'trailer_id' => 'required|string|size:36',
            'trailer_label' => 'nullable|string|max:100',
            'trailer_plate' => 'nullable|string|max:50',
            'current_trailer_chip' => 'nullable|string|max:64',
            'load_state' => ['required', 'string', Rule::in(ParkingLoadState::all())],
            'linked_order_id' => 'nullable|string|size:36',
            'linked_order_no' => 'nullable|string|max:50',
            'active_visit_id' => 'nullable|string|size:36',
            'active_visit_no' => 'nullable|string|max:50',
            'driver_id' => 'nullable|string|size:36',
            'driver_name' => 'nullable|string|max:255',
            'tractor_plate' => 'nullable|string|max:50',
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}

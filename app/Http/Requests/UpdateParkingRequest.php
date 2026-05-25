<?php

namespace App\Http\Requests;

use App\Enums\ParkingLoadState;
use App\Enums\ParkingNextAction;
use App\Enums\ParkingSlotStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParkingRequest extends FormRequest
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
            'code' => 'sometimes|required|string|max:50|unique:parkings,code,' . $id . ',id',
            'name' => 'sometimes|required|string|max:100',
            'site_id' => 'nullable|string|size:36',
            'plant_area_id' => 'nullable|string|size:36',
            'plant_configuration_id' => 'nullable|string|size:36',

            'slot_status' => ['nullable', 'string', Rule::in(ParkingSlotStatus::all())],

            'current_trailer_id' => 'nullable|string|size:36',
            'current_trailer_label' => 'nullable|string|max:100',
            'current_trailer_plate' => 'nullable|string|max:50',
            'current_trailer_chip' => 'nullable|string|max:64',
            'current_load_state' => ['nullable', 'string', Rule::in(ParkingLoadState::all())],

            'linked_order_id' => 'nullable|string|size:36',
            'linked_order_no' => 'nullable|string|max:50',
            'active_visit_id' => 'nullable|string|size:36',
            'active_visit_no' => 'nullable|string|max:50',
            'driver_id' => 'nullable|string|size:36',
            'driver_name' => 'nullable|string|max:255',
            'tractor_plate' => 'nullable|string|max:50',

            'parked_since' => 'nullable|date',
            'reserved_for' => 'nullable|date',

            'blocker_reason' => 'nullable|string|max:5000',
            'clarification_case_id' => 'nullable|string|size:36',

            'next_action' => ['nullable', 'string', Rule::in(ParkingNextAction::all())],
            'document_summary' => 'nullable|array',

            'is_active' => 'sometimes|boolean',
        ];
    }
}

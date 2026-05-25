<?php

namespace App\Http\Requests\PlantVisit;

use App\Enums\DriverTask;
use App\Enums\PlantVisitLocation;
use App\Enums\PlantVisitStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePlantVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules(): array
    {
        return [
            // Soft FKs — validated as existence when present.
            'driver_id' => 'nullable|string|size:36|exists:drivers,id',
            'trailer_id' => 'nullable|string|size:36|exists:trailers,id',
            'tractor_vehicle_id' => 'nullable|string|size:36|exists:tractor_vehicles,id',
            'order_id' => 'nullable|string|size:36|exists:loading_orders,id',

            // Captured at the Driver Terminal — authoritative plate during visit.
            'tractor_plate' => 'nullable|string|max:50',

            // Workflow concepts — kept INDEPENDENT (V1.6 §2).
            'task_flow' => ['nullable', Rule::in(DriverTask::all())],
            'current_step' => ['nullable', Rule::in(PlantVisitStep::all())],
            'current_location' => ['nullable', Rule::in(PlantVisitLocation::all())],

            'next_action_label' => 'nullable|string|max:255',
            'next_action_target' => 'nullable|string|max:255',
            'owner_role' => 'nullable|string|max:50',

            'entry_time' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}

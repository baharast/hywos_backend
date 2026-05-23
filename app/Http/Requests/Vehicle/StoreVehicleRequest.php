<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules(): array
    {
        return [
            'license_plate' => 'required|string|max:20|unique:tractor_vehicles,license_plate',
            'vehicle_type' => ['required', 'string', Rule::in(VehicleType::all())],

            'vehicle_code' => 'nullable|string|max:50|unique:tractor_vehicles,vehicle_code',
            'plate_country' => 'nullable|string|max:5',

            'carrier_id' => 'nullable|string|size:36|exists:companies,id',
            'owner_name' => 'nullable|string|max:255',
            'default_driver_id' => 'nullable|string|size:36|exists:drivers,id',

            'vin' => 'nullable|string|max:50',
            'registration_expiry' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',

            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ];
    }
}

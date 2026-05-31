<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Strip any client-supplied `vehicle_code` BEFORE validation. The
     * code is a system identifier minted at create time and immutable
     * for the row's lifetime — drop it silently rather than failing.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('vehicle_code')) {
            $this->offsetUnset('vehicle_code');
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'license_plate' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('tractor_vehicles', 'license_plate')->ignore($id),
            ],
            'vehicle_type' => ['sometimes', 'required', 'string', Rule::in(VehicleType::all())],

            // vehicle_code intentionally absent — see prepareForValidation().
            // Identifier is system-managed and cannot be edited via PUT.
            'plate_country' => 'nullable|string|max:5',

            'carrier_id' => 'nullable|string|size:36|exists:companies,id',
            'owner_name' => 'nullable|string|max:255',
            'default_driver_id' => 'nullable|string|size:36|exists:drivers,id',

            'vin' => 'nullable|string|max:50',
            'registration_expiry' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',

            'notes' => 'nullable|string',
            // status, block_reason, blocked_at are intentionally NOT accepted here.
            // Use the dedicated block/unblock endpoints.
        ];
    }
}

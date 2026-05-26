<?php

namespace App\Http\Requests\CalibrationProfile;

use App\Enums\CalibrationLockoutBehavior;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a draft Calibration Profile (V2.1 §5).
 *
 * `device_bmk` is the BMK tag from the Analysis Devices registry (e.g.
 * AN-OS-01). The service tries to resolve a matching `analysis_devices`
 * row by BMK and stamps `device_id` + `device_name` as snapshot columns.
 * V2.1 §5.1: device options must come from the registry — the FE is
 * expected to populate the dropdown from there.
 */
class CreateCalibrationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_bmk' => 'required|string|max:50',
            'device_name' => 'nullable|string|max:255',
            'calibration_medium' => 'required|string|max:100',
            'certificate_ref' => 'nullable|string|max:100',
            'profile_version' => 'nullable|string|max:20',
            'lockout_behavior' => 'required|in:' . implode(',', CalibrationLockoutBehavior::all()),
            'medium_expiry_at' => 'nullable|date',
            'next_due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
